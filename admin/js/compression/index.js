/* global ajaxurl */
/* global appLocalizer */
jQuery(function ($) {
    'use strict'; 

    /**
     * Class constructor.
     * @param {Object}  button  Button object that made the call.
     * @param {boolean} bulk    Bulk compression or not.
     * @param {string}  type    Accepts: 'media'.
     */
    const compression = function (button, bulk, type = 'media') {

        // Compressed and total we take from the progress bar... I don't like this :-(
        const progressBar = jQuery(
            '.bulk-compression-wrapper .wpf-progress-state-text'
        );

        appLocalizer.__compressed = parseInt(
            progressBar.find('span:first-child').html()
        );

        appLocalizer.__total = parseInt(progressBar.find('span:last-child').html());

        // TODO: errors will reset after bulk compression limit is reached and user clicks continue.
        appLocalizer.__errors = [];

        appLocalizer.__log = jQuery('.compression-final-log');

        appLocalizer.__perf = 0;

        appLocalizer.__deferred = jQuery.Deferred();

        appLocalizer.__deferred.errors = [];

        appLocalizer.__is_bulk = false;

        appLocalizer.__bulk_ajax_suffix = 'wp_compressit_bulk';

        appLocalizer.__single_ajax_suffix = 'wp_compressit_manual';

        appLocalizer.__ids = [];

        appLocalizer.__button = jQuery(button[0]);

        appLocalizer.__is_bulk = typeof bulk ? bulk : false;

        appLocalizer.__url = apiUrl('wp_compressit_manual');

        //If compression attribute is not defined, Need not skip re-Compress IDs.
        appLocalizer.skip_recompress = !(
            'undefined' === typeof appLocalizer.__button.data('compression') ||
            !appLocalizer.__button.data('compression')
        );

        setIds();

        appLocalizer.__is_bulk_recompress =
            0 < appLocalizer.recompress.length && !appLocalizer.skip_recompress;

        appLocalizer.__status = appLocalizer.__button.parent().prev('.compression-status');

        // Added for Media gallery support.
        appLocalizer.__compression_type = type;

        start();

        run();

        bindDeferredEvents();

        // Handle cancel ajax.
        cancelAjax();

        return appLocalizer.__deferred;

    };

    /**
     * Sets ids.
     */
    const setIds = function () {
        let _ids = [];
        if (0 < appLocalizer.recompress.length && !appLocalizer.skip_recompress) {
            if (0 < appLocalizer.uncompressed.length) {
                _ids = appLocalizer.recompress.concat(appLocalizer.uncompressed);
            } else {
                _ids = appLocalizer.recompress;
            }
        } else {
            _ids = appLocalizer.uncompressed;
        }

        if ('object' === typeof _ids) {
            // If button has re-Compress class, and we do have ids that needs to re-Compressed, put them in the list.
            appLocalizer.__ids = _ids.filter(function (itm, i, a) {
                return i === a.indexOf(itm);
            });
        } else {
            appLocalizer.__ids = _ids;
        }
    }

    /**
     * Show loader in button for single and bulk Compression.
     */
    const start = function () {
        appLocalizer.__button.prop('disabled', true);
        appLocalizer.__button.addClass('wpfiles-started');
        singleStart();
        bulkStart();
    }

    /**
     * Start single image Compression.
     */
    const singleStart = function () {
        if (appLocalizer.__is_bulk) {
            return;
        }

        appLocalizer.__button.html(
            '<span class="spinner wpfiles-progress">' +
            appLocalizer.wpfiles_msgs.compressing +
            '</span>'
        );
        appLocalizer.__status.removeClass('error');
    }

    /**
     * Send ajax request for single and bulk Compressing.
     */
    const run = function () {
        // If bulk and we have a definite number of IDs.
        if (appLocalizer.__is_bulk && appLocalizer.__ids.length > 0) {
            callAjax();
        }

        if (!appLocalizer.__is_bulk) {
            callAjax();
        }
    }

    /**
     * Send ajax request for optimizing single and bulk, call update_progress on ajax response.
     * @return {*}  Ajax call response.
     */
    const callAjax = function () {
        /**
         * This here little piece of code allows to track auto continue clicks and halts bulk Compressing until the page
         * is reloaded.
         * @since 1.0.0
         * @see https://wordpress.org/plugins/wp-nonstop-compressit/
         */
        if (
            0 !== appLocalizer.__perf &&
            'undefined' !== typeof appLocalizer.__perf &&
            10 > performance.now() - appLocalizer.__perf
        ) {
            freeExceeded();
            return appLocalizer.__deferred;
        }

        let nonceValue = '';

        // Remove from array while processing so we can continue where left off.
        appLocalizer.__current_id = appLocalizer.__is_bulk
            ? appLocalizer.__ids.shift()
            : appLocalizer.__button.data('id');

        // Remove the ID from respective variable as well.
        updateCompressionIds(appLocalizer.__current_id);

        const nonceField = appLocalizer.__button.parent().find('#_wpfiles_nonce');

        if (nonceField) {
            nonceValue = nonceField.val();
        }

        appLocalizer.__request = ajax(
            appLocalizer.__is_bulk_recompress,
            appLocalizer.__current_id,
            appLocalizer.__url,
            nonceValue
        )
            .done(function (res) {
                // If no response or success is false, do not process further. Increase the error count except if bulk request limit exceeded.
                if (
                    'undefined' === typeof res.success ||
                    ('undefined' !== typeof res.success &&
                        false === res.success &&
                        'undefined' !== typeof res.data &&
                        'limit_exceeded' !== res.data.error)
                ) {
                    appLocalizer.__errors.push(appLocalizer.__current_id);

                    /** @param {string} res.data.file_name */
                    const errorMsg = prepareErrorRow(
                        res.data.error_message,
                        res.data.file_name,
                        res.data.thumbnail,
                        appLocalizer.__current_id,
                        appLocalizer.__compression_type
                    );

                    appLocalizer.__log.show();

                    if (appLocalizer.__errors.length > 5) {
                        jQuery('.compression-bulk-errors-actions').removeClass(
                            'wpf-hidden'
                        );
                    } else {
                        // Print the error on screen.
                        appLocalizer.__log
                            .find('.compression-bulk-errors')
                            .append(errorMsg);
                    }
                } else if (
                    'undefined' !== typeof res.success &&
                    res.success
                ) {
                    // Increment the compressed count if image compressed without errors.
                    appLocalizer.__compressed++;
                }

                // Check whether to show the warning notice or not.
                subscriptionValidity(res.data);

                /**
                 * Bulk Compression limit exceeded: Stop ajax requests, remove progress bar, append the last image ID
                 * back to Compression variable, and reset variables to allow the user to continue bulk Compression.
                 */
                if (
                    'undefined' !== typeof res.data &&
                    'limit_exceeded' === res.data.error &&
                    'resolved' !== appLocalizer.__deferred.state()
                ) {
                    // Show error message.
                    const bulkWarning = document.getElementById(
                        'bulk_compression_warning'
                    );
                    bulkWarning.classList.remove('wpf-hidden');

                    // Add a data attribute to the Compression button, to stop sending ajax.
                    appLocalizer.__button.attr('continue_compression', false);

                    // Reinsert the current ID.
                    appLocalizer.uncompressed.unshift(appLocalizer.__current_id);
                    appLocalizer.__ids.unshift(appLocalizer.__current_id);

                    appLocalizer.__perf = performance.now();
                    freeExceeded();
                } else if (appLocalizer.__is_bulk) {
                    updateProgress(res);
                } else if (0 === appLocalizer.__ids.length) {
                    // Sync stats anyway.
                    syncStats();
                }

                singleDone();
            })
            .always(function () {
                if (!_continue() || !appLocalizer.__is_bulk) {
                    // Calls deferred.done()
                    appLocalizer.__deferred.resolve();
                } else {
                    callAjax();
                }
            });

        appLocalizer.__deferred.errors = appLocalizer.__errors;
        return appLocalizer.__deferred;
    }

    /**
     * Free Compression limit exceeded.
     */
    const freeExceeded = function () {
        const progress = jQuery('.wpfiles-bulk-progress-bar-wrapper');
        progress.addClass('wpfiles-exceed-limit');
        progress
            .find('.wpf-progress-block .wpfiles-cancel-bulk')
            .addClass('wpf-hidden');
        progress
            .find('.wpf-progress-block .wpfiles-all')
            .removeClass('wpf-hidden');

        progress
            .find('i.wpf-icon-loader')
            .addClass('wpf-icon-info')
            .removeClass('wpf-icon-loader')
            .removeClass('wpf-loading');

        document
            .getElementById('bulk-compression-resume-button')
            .classList.remove('wpf-hidden');

        if (document.getElementById('compression-box-bulk-upgrade')) {
            document
                .getElementById('compression-box-bulk-upgrade')
                .classList.remove('wpf-hidden');
        }
    }

    /**
     * Remove the current ID from the unCompressed/re-Compress variable.
     *
     * @param {number} currentId
     */
    const updateCompressionIds = function (currentId) {
        if (
            'undefined' !== typeof appLocalizer.uncompressed &&
            appLocalizer.uncompressed.length > 0
        ) {
            const uIndex = appLocalizer.uncompressed.indexOf(currentId);
            if (uIndex > -1) {
                appLocalizer.uncompressed.splice(uIndex, 1);
            }
        }

        // Remove from the re-Compress list.
        if (
            'undefined' !== typeof appLocalizer.recompress &&
            appLocalizer.recompress.length > 0
        ) {
            const index = appLocalizer.recompress.indexOf(currentId);
            if (index > -1) {
                appLocalizer.recompress.splice(index, 1);
            }
        }
    }

    /**
     * Send Ajax request for compressing the image.
     *
     * @param {boolean} isBulkRecompress
     * @param {number}  id
     * @param {string}  sendUrl
     * @param {string}  nonce
     * @return {*|jQuery.promise|void}  Compression results.
     */
    const ajax = function (isBulkRecompress, id, sendUrl, nonce) {
        const param = jQuery.param({
            is_bulk_recompress: isBulkRecompress,
            attachment_id: id,
            _nonce: nonce,
        });

        return jQuery.ajax({
            type: 'POST',
            data: param,
            url: sendUrl,
            headers: requestHeaders('POST'),
            /** @param {Array} appLocalizer */
            timeout: appLocalizer.timeout,
            dataType: 'json',
        });
    }

    /**
     * Check subscription validity.
     *
     * @param {Object} data
     * @param {number} data.subscription_failed
     */
    const subscriptionValidity = function (data) {
        const memberValidityNotice = jQuery('#wpfiles-invalid-member');

        // Check for subscription warning.
        if (
            'undefined' !== typeof data &&
            'undefined' !== typeof data.subscription_failed &&
            memberValidityNotice.length > 0
        ) {
            if (data.subscription_failed) {
                memberValidityNotice.show();
            } else {
                memberValidityNotice.hide();
            }
        }
    }

    /**
     * Finish single image Compression.
     */
    const singleDone = function () {
        if (appLocalizer.__is_bulk) {
            return;
        }

        const self = this;

        appLocalizer.__button.html(appLocalizer.wpfiles_msgs.all_done);

        appLocalizer.__request
            .done(function (response) {
                if ('undefined' !== typeof response.data) {
                    // Check if stats div exists.
                    const parent = appLocalizer.__status.parent();

                    // Check whether to show subscription validity notice or not.
                    subscriptionValidity(response.data);

                    if (!response.success) {
                        appLocalizer.__status.addClass('error');
                        /** @param {string} response.data.error */
                        appLocalizer.__status.html(response.data.error);
                        appLocalizer.__button.html(
                            window.compression_vars.strings.stats_label
                        );
                    } else {
                        // If we've updated status, replace the content.
                        parent.html(response.data);
                    }

                    /**
                     * Update image size in attachment info panel.
                     * @param {string|number} response.data.new_size
                     */
                    updateImageStats(response.data.new_size);
                }
                enableButton();
            })
            .fail(function (response) {
                appLocalizer.__status.html(response.data);
                appLocalizer.__status.addClass('error');
                enableButton();
            });
    }

    /**
     * Update image size in attachment info panel.
     * @since 1.0
     * @param {number} newSize
     */
    const updateImageStats = function (newSize) {
        if (0 === newSize) {
            return;
        }

        const attachmentSize = jQuery('.attachment-info .file-size');

        const currentSize = attachmentSize
            .contents()
            .filter(function () {
                return this.nodeType === 3;
            })
            .text();

        // There is a space before the size.
        if (currentSize !== ' ' + newSize) {
            const sizeStrongEl = attachmentSize
                .contents()
                .filter(function () {
                    return this.nodeType === 1;
                })
                .text();
            attachmentSize.html(
                '<strong>' + sizeStrongEl + '</strong> ' + newSize
            );
        }
    }

    /**
     * Enable button.
     */
    const enableButton = function () {
        appLocalizer.__button.prop('disabled', false);
        jQuery('.wpfiles-all').prop('disabled', false);
        // For bulk process, enable other buttons.
        jQuery(
            'button.wpfiles-scan, a.wpfiles-lossy-enable, button.wpfiles-resize-enable, button#save-settings-button'
        ).prop('disabled', false);
    }

    /**
 * Sync stats.
 */
    const syncStats = function () {
        const messageHolder = jQuery(
            'div.wpfiles-bulk-progress-bar-wrapper div.wpfiles-count.tc'
        );
        // Store the existing content in a variable.
        const progressMessage = messageHolder.html();
        /** @param {string} appLocalizer.wpfiles_msgs.sync_stats */
        messageHolder.html(appLocalizer.wpfiles_msgs.sync_stats);

        // Send ajax.
        jQuery
            .ajax({
                type: 'GET',
                url: appLocalizer.__url,
                data: {
                    action: 'get_stats',
                },
                success(response) {
                    if (response && 'undefined' !== typeof response) {
                        response = response.data;
                        jQuery.extend(appLocalizer, {
                            count_images: response.count_images,
                            count_compressed: response.count_compressed,
                            count_total: response.count_total,
                            count_resize: response.count_resize,
                            count_supercompressed: response.count_supercompressed,
                            savings_bytes: response.savings_bytes,
                            savings_conversion: response.savings_conversion,
                            savings_resize: response.savings_resize,
                            size_before: response.size_before,
                            size_after: response.size_after,
                        });
                        // Got the stats, update it.
                        updateStats(appLocalizer.__compression_type);
                    }
                },
            })
            .always(() => messageHolder.html(progressMessage));
    }

    /**
     * Update all stats sections based on the response.
     * @param {string} scanType Current scan type.
     */
    const updateStats = function (scanType) {
        let superSavings = 0;

        // Calculate updated savings in bytes.
        appLocalizer.savings_bytes =
            parseInt(appLocalizer.size_before) -
            parseInt(appLocalizer.size_after);

        const formattedSize = helpers.formatBytes(
            appLocalizer.savings_bytes,
            1
        );
        const statsHuman = jQuery('.wpfiles-savings .wpfiles-stats-human');

        statsHuman.html(
            helpers.getFormatFromString(formattedSize)
        );
        jQuery('.wpf-summary-large.wpfiles-stats-human').html(
            helpers.getSizeFromString(formattedSize)
        );

        // Update the savings percent.
        appLocalizer.savings_percent = helpers.precise_round(
            (parseInt(appLocalizer.savings_bytes) /
                parseInt(appLocalizer.size_before)) *
            100,
            1
        );
        if (!isNaN(appLocalizer.savings_percent)) {
            jQuery('.wpfiles-savings .wpfiles-stats-percent').html(
                appLocalizer.savings_percent
            );
        }

        // Super-Compression savings.
        if (
            'undefined' !== typeof appLocalizer.savings_bytes &&
            'undefined' !== typeof appLocalizer.savings_resize
        ) {
            superSavings =
                parseInt(appLocalizer.savings_bytes) -
                parseInt(appLocalizer.savings_resize);
            if (superSavings > 0) {
                jQuery(
                    'li.super-compression-attachments span.compressed-savings'
                ).html(helpers.formatBytes(superSavings, 1));
            }
        }

        // Update image count.
        jQuery(
            'span.compressed-items-count span.wpfiles-count-total span.wpfiles-total-optimized'
        ).html(appLocalizer.count_images);

        // Update resize image count.
        jQuery(
            'span.compressed-items-count span.wpfiles-count-resize-total span.wpfiles-total-optimized'
        ).html(appLocalizer.count_resize);

        // Update super-Compressed image count.
        const compressedCountDiv = jQuery(
            'li.super-compression-attachments .compressed-count'
        );
        if (
            compressedCountDiv.length &&
            'undefined' !== typeof appLocalizer.count_supercompressed
        ) {
            compressedCountDiv.html(appLocalizer.count_supercompressed);
        }

        // Update conversion savings.
        const compressionConversionSavings = jQuery('.compression-conversion-savings');
        if (
            compressionConversionSavings.length > 0 &&
            'undefined' !== typeof appLocalizer.savings_conversion &&
            appLocalizer.savings_conversion !== ''
        ) {
            const conversionSavings = compressionConversionSavings.find(
                '.wpfiles-stats'
            );
            if (conversionSavings.length > 0) {
                conversionSavings.html(
                    helpers.formatBytes(
                        appLocalizer.savings_conversion,
                        1
                    )
                );
            }
        }

        // Update resize savings.
        const compressionResizeSavings = jQuery('.compression-resize-savings');
        if (
            compressionResizeSavings.length > 0 &&
            'undefined' !== typeof appLocalizer.savings_resize &&
            appLocalizer.savings_resize !== ''
        ) {
            // Get the resize savings in number.
            const savingsValue = parseInt(appLocalizer.savings_resize);
            const resizeSavings = compressionResizeSavings.find('.wpfiles-stats');
            const resizeMessage = compressionResizeSavings.find(
                '.wpfiles-stats-label-message'
            );
            // Replace only if value is grater than 0.
            if (savingsValue > 0 && resizeSavings.length > 0) {
                // Hide message.
                if (resizeMessage.length > 0) {
                    resizeMessage.hide();
                }
                resizeSavings.html(
                    helpers.formatBytes(
                        appLocalizer.savings_resize,
                        1
                    )
                );
            }
        }

        //Update pro Savings
        setProSavings();

        // Updating pro savings stats.
        if ('undefined' !== typeof appLocalizer.pro_savings) {
            // Pro stats section.
            const compressionProSavings = jQuery('.compression-avg-pro-savings');
            if (compressionProSavings.length > 0) {
                const proSavingsPercent = compressionProSavings.find(
                    '.wpfiles-stats-percent'
                );
                const proSavingsBytes = compressionProSavings.find(
                    '.wpfiles-stats-human'
                );
                if (
                    proSavingsPercent.length > 0 &&
                    'undefined' !==
                    typeof appLocalizer.pro_savings.percent &&
                    appLocalizer.pro_savings.percent !== ''
                ) {
                    proSavingsPercent.html(
                        appLocalizer.pro_savings.percent
                    );
                }
                if (
                    proSavingsBytes.length > 0 &&
                    'undefined' !==
                    typeof appLocalizer.pro_savings.savings_bytes &&
                    appLocalizer.pro_savings.savings_bytes !== ''
                ) {
                    proSavingsBytes.html(
                        appLocalizer.pro_savings.savings_bytes
                    );
                }
            }
        }

        // Update remaining count.
        // Update sidebar count.
        const sidenavCountDiv = jQuery(
            '.compression-sidenav .wpfiles-remaining-count'
        );
        if (sidenavCountDiv.length) {
            let count = 0;

            // Uncompressed
            if (
                'undefined' !== typeof appLocalizer.uncompressed &&
                appLocalizer.uncompressed.length > 0
            ) {
                count += appLocalizer.uncompressed.length;
            }

            // Re-compress
            if (
                'undefined' !== typeof appLocalizer.recompress &&
                appLocalizer.recompress.length > 0
            ) {
                count += appLocalizer.recompress.length;
            }

            updateRemainingCount(count);
        }
    }

    /**
     * Update remaining count.
     */
    const updateRemainingCount = function (count) {
        const remainingCountContainers = jQuery('.wpfiles-remaining-count');
        if (remainingCountContainers.length) {
            remainingCountContainers.html(count);
        }

        // Update sidebar count.
        const sidenavCountDiv = jQuery('.compression-sidenav .wpfiles-remaining-count'),
            sidenavCheckTag = jQuery('.compression-sidenav .compression-bulk .wpf-icon-check-tick');
        if (sidenavCountDiv.length && sidenavCheckTag.length) {
            if (count > 0) {
                sidenavCountDiv.removeClass('wpf-hidden');
                sidenavCheckTag.addClass('wpf-hidden');
            } else {
                jQuery('.wpf-summary-compression .compression-stats-icon').addClass('wpf-hidden');
                sidenavCheckTag.removeClass('wpf-hidden');
                sidenavCountDiv.addClass('wpf-hidden');
            }
        }
    }

    /**
     * Update progress.
     *
     * @param {Object} _res
     */
    const updateProgress = function (_res) {
        if (!appLocalizer.__is_bulk_recompress && !appLocalizer.__is_bulk) {
            return;
        }

        let progress = 0;

        // Update localized stats.
        if (
            _res &&
            'undefined' !== typeof _res.data &&
            'undefined' !== typeof _res.data.stats
        ) {
            updateLocalizedStats(_res.data.stats, appLocalizer.__compression_type);
        }

        if (!appLocalizer.__is_bulk_recompress) {
            // Handle progress for normal bulk compression.
            progress =
                ((appLocalizer.__compressed + appLocalizer.__errors.length) / appLocalizer.__total) * 100;
        } else {
            // If the request was successful, update the progress bar.
            if (_res.success) {
                // Handle progress for super Compression progress bar.
                if (appLocalizer.recompress.length > 0) {
                    // Update the count.
                    jQuery('.wpfiles-images-remaining').html(
                        appLocalizer.recompress.length
                    );
                } else if (
                    0 === appLocalizer.recompress.length &&
                    0 === appLocalizer.__ids.length
                ) {
                    // If all images are re-Compressed, show the All Compressed message.
                    jQuery(
                        '.bulk-recompress-wrapper .wpfiles-all-done, .wpfiles-pagespeed-recommendation'
                    ).removeClass('wpf-hidden');

                    // Hide everything else.
                    jQuery(
                        '.wpfiles-recompress-wrap, .wpfiles-bulk-progress-bar-wrapper'
                    ).addClass('wpf-hidden');
                }
            }

            // Handle progress for normal bulk Compression. Set progress bar width.
            if (
                'undefined' !== typeof appLocalizer.__ids &&
                'undefined' !== typeof appLocalizer.__total &&
                appLocalizer.__total > 0
            ) {
                progress =
                    ((appLocalizer.__compressed + appLocalizer.__errors.length) / appLocalizer.__total) *
                    100;
            }
        }

        // No more images left. Show bulk wrapper and Compression notice.
        if (0 === appLocalizer.__ids.length) {
            // Sync stats for bulk Compression media library.
            syncStats();
            jQuery(
                '.bulk-compression-wrapper .wpfiles-all-done, .wpfiles-pagespeed-recommendation'
            ).removeClass('wpf-hidden');
            jQuery('.wpfiles-bulk-wrapper').addClass('wpf-hidden');
        }

        // Update remaining count.
        if ('undefined' !== typeof appLocalizer.__ids) {
            updateRemainingCount(appLocalizer.__ids.length);
        }

        // Increase the progress bar and counter.
        _updateProgress(
            appLocalizer.__compressed + appLocalizer.__errors.length,
            helpers.precise_round(progress, 1)
        );

        // Avoid updating the stats twice when the bulk compression ends on Compression's page.
        if (0 !== appLocalizer.__ids.length) {
            // Update stats and counts.
            updateStats(appLocalizer.__compression_type);
        }
    }

    /**
     * Update progress.
     * @param {number} count  Number of images optimized.
     * @param {string} width  Percentage complete.
     * @private
     */
    const _updateProgress = function (count, width) {
        if (!appLocalizer.__is_bulk && !appLocalizer.__is_bulk_recompress) {
            return;
        }

        // Progress bar label.
        jQuery('span.wpfiles-images-percent').html(width + '%');
        // Progress bar.
        jQuery('.bulk-compression-wrapper .wpfiles-progress-inner').css(
            'width',
            width + '%'
        );

        // Progress bar status.
        jQuery('.bulk-compression-wrapper .wpf-progress-state-text')
            .find('span:first-child')
            .html(count)
            .find('span:last-child')
            .html(appLocalizer.__total);
    }

    /**
     * Whether to send the ajax requests further or not.
     * @return {*|boolean}  Should continue or not.
     */
    const _continue = function () {
        let continueCompression = appLocalizer.__button.attr('continue_compression');

        if ('undefined' === typeof continueCompression) {
            continueCompression = true;
        }

        if ('false' === continueCompression || !continueCompression) {
            continueCompression = false;
        }

        return continueCompression && appLocalizer.__ids.length > 0 && appLocalizer.__is_bulk;
    }

    /**
     * Handles the cancel button click.
     * Update the UI, and enable the bulk Compression button.
     */
    const cancelAjax = function () {
        const self = this;

        jQuery('.wpfiles-cancel-bulk').on('click', function () {
            // Add a data attribute to the Compression button, to stop sending ajax.
            appLocalizer.__button.attr('continue_compression', false);
            // Sync and update stats.
            syncStats();

            appLocalizer.__request.abort();
            enableButton();
            appLocalizer.__button.removeClass('wpfiles-started');
            appLocalizer.uncompressed.unshift(appLocalizer.__current_id);
            jQuery('.wpfiles-bulk-wrapper').removeClass('wpf-hidden');

            // Hide the progress bar.
            jQuery('.wpfiles-bulk-progress-bar-wrapper').addClass('wpf-hidden');
        });
    }

    /**
     * Prepare error row. Will only allow to hide errors for WP media attachments.
     * @since 1.0.0
     * @param {string} errorMsg   Error message.
     * @param {string} fileName   File name.
     * @param {string} thumbnail  Thumbnail for image (if available).
     * @param {number} id         Image ID.
     * @param {string} type       Compression type: media or netxgen.
     *
     * @return {string}  Row with error.
     */
    const prepareErrorRow = function (errorMsg, fileName, thumbnail, id, type) {
        const thumbDiv =
            'undefined' === typeof thumbnail
                ? '<i class="wpf-icon-photo-picture" aria-hidden="true"></i>'
                : thumbnail;
        const fileLink =
            'undefined' === fileName || 'undefined' === typeof fileName
                ? 'undefined'
                : fileName;

        let tableDiv =
            '<div class="compression-bulk-error-row">' +
            '<div class="compression-bulk-image-data">' +
            thumbDiv +
            '<span class="compression-image-name">' +
            fileLink +
            '</span>' +
            '<span class="compression-image-error">' +
            errorMsg +
            '</span>' +
            '</div>';

        if ('media' === type) {
            tableDiv =
                tableDiv +
                '<div class="compression-bulk-image-actions">' +
                '<button type="button" class="wpf-button-icon wpf-tooltip wpf-tooltip-constrained wpf-tooltip-top-right compression-ignore-image" wf-tooltip="' +
                appLocalizer.wpfiles_msgs.error_ignore +
                '" data-id="' +
                id +
                '">' +
                '<i class="wpf-icon-eye-hide" aria-hidden="true"></i>' +
                '</button>' +
                '</div>';
        }

        tableDiv = tableDiv + '</div>';

        return tableDiv;
    }

    /**
     * After the bulk optimization has been finished.
     */
    const bulkDone = function () {
        if (!appLocalizer.__is_bulk) {
            return;
        }

        // Enable the button.
        enableButton();

        const statusIcon = jQuery('.wpf-summary-compression .compression-stats-icon');

        // Show notice.
        if (0 === appLocalizer.__ids.length) {
            statusIcon.addClass('wpf-hidden');
            jQuery(
                '.bulk-compression-wrapper .wpfiles-all-done, .wpfiles-pagespeed-recommendation'
            ).removeClass('wpf-hidden');
            jQuery('.wpfiles-bulk-wrapper').addClass('wpf-hidden');
            // Hide the progress bar if scan is finished.
            jQuery('.wpfiles-bulk-progress-bar-wrapper').addClass('wpf-hidden');

            // Display the upsell metabox.
            if (document.getElementById('compression-box-bulk-upgrade')) {
                document
                    .getElementById('compression-box-bulk-upgrade')
                    .classList.remove('wpf-hidden');

                document
                    .getElementById('wpfiles-all-compressed-text')
                    .classList.remove('wpf-hidden');

                document
                    .getElementById('wpfiles-pending-to-compression-text')
                    .classList.add('wpf-hidden');
            }

            // Reset the progress when we finish so the next compressing starts from zero.
            _updateProgress(0, 0);
        } else {
            // Show loader.
            statusIcon
                .removeClass('wpf-icon-loader wpf-loading wpf-hidden')
                .addClass('wpf-icon-info wpf-warning');

            const notice = jQuery(
                '.bulk-compression-wrapper .wpfiles-recompress-notice'
            );

            if (notice.length > 0) {
                notice.show();
            } else {
                jQuery('.bulk-compression-wrapper .wpfiles-remaining').removeClass('wpf-hidden');
            }
        }

        // Enable re-Compress and scan button.
        jQuery('.wp-recompress.wpfiles-action, .wpfiles-scan').removeProp(
            'disabled'
        );
    }

    /**
     * Adds the stats for the current image to existing stats.
     * @param {Array}   imageStats
     * @param {string}  imageStats.count
     * @param {boolean} imageStats.is_lossy
     * @param {Array}   imageStats.savings_resize
     * @param {Array}   imageStats.savings_conversion
     * @param {string}  imageStats.size_before
     * @param {string}  imageStats.size_after
     * @param {string}  type
     */
    const updateLocalizedStats = function (imageStats, type) {
        // Increase the Compression count.
        if ('undefined' === typeof window.appLocalizer) {
            return;
        }

        // No need to increase attachment count, resize, conversion savings for directory Compression.
        if ('media' === type) {
            // Increase Compressed image count.
            appLocalizer.count_images =
                parseInt(appLocalizer.count_images) +
                parseInt(imageStats.count);

            // Increase super Compression count, if applicable.
            if (imageStats.is_lossy) {
                appLocalizer.count_supercompressed =
                    parseInt(appLocalizer.count_supercompressed) + 1;
            }

            // Add to resize savings.
            appLocalizer.savings_resize =
                'undefined' !== typeof imageStats.savings_resize.bytes
                    ? parseInt(appLocalizer.savings_resize) +
                    parseInt(imageStats.savings_resize.bytes)
                    : parseInt(appLocalizer.savings_resize);

            // Update resize count.
            appLocalizer.count_resize =
                'undefined' !== typeof imageStats.savings_resize.bytes
                    ? parseInt(appLocalizer.count_resize) + 1
                    : appLocalizer.count_resize;

            // Add to conversion savings.
            appLocalizer.savings_conversion =
                'undefined' !== typeof imageStats.savings_conversion &&
                    'undefined' !== typeof imageStats.savings_conversion.bytes
                    ? parseInt(appLocalizer.savings_conversion) +
                    parseInt(imageStats.savings_conversion.bytes)
                    : parseInt(appLocalizer.savings_conversion);
        } else if ('directory_compression' === type) {
            //Increase compressed image count
            appLocalizer.count_images =
                parseInt(appLocalizer.count_images) + 1;
        }

        // If we have savings. Update savings.
        if (imageStats.size_before > imageStats.size_after) {
            appLocalizer.size_before =
                'undefined' !== typeof imageStats.size_before
                    ? parseInt(appLocalizer.size_before) +
                    parseInt(imageStats.size_before)
                    : parseInt(appLocalizer.size_before);
            appLocalizer.size_after =
                'undefined' !== typeof imageStats.size_after
                    ? parseInt(appLocalizer.size_after) +
                    parseInt(imageStats.size_after)
                    : parseInt(appLocalizer.size_after);
        }

        // Add stats for resizing. Update savings.
        if ('undefined' !== typeof imageStats.savings_resize) {
            appLocalizer.size_before =
                'undefined' !== typeof imageStats.savings_resize.size_before
                    ? parseInt(appLocalizer.size_before) +
                    parseInt(imageStats.savings_resize.size_before)
                    : parseInt(appLocalizer.size_before);
            appLocalizer.size_after =
                'undefined' !== typeof imageStats.savings_resize.size_after
                    ? parseInt(appLocalizer.size_after) +
                    parseInt(imageStats.savings_resize.size_after)
                    : parseInt(appLocalizer.size_after);
        }

        // Add stats for conversion. Update savings.
        if ('undefined' !== typeof imageStats.savings_conversion) {
            appLocalizer.size_before =
                'undefined' !== typeof imageStats.savings_conversion.size_before
                    ? parseInt(appLocalizer.size_before) +
                    parseInt(imageStats.savings_conversion.size_before)
                    : parseInt(appLocalizer.size_before);
            appLocalizer.size_after =
                'undefined' !== typeof imageStats.savings_conversion.size_after
                    ? parseInt(appLocalizer.size_after) +
                    parseInt(imageStats.savings_conversion.size_after)
                    : parseInt(appLocalizer.size_after);
        }
    }

    /**
     * Set pro savings stats if not premium user.
     * For non-premium users, show expected average savings based
     * on the free version savings.
     */
    const setProSavings = function () {
        // Default values.
        let savings =
            appLocalizer.savings_percent > 0
                ? appLocalizer.savings_percent
                : 0,
            savingsBytes =
                appLocalizer.savings_bytes > 0
                    ? appLocalizer.savings_bytes
                    : 0,
            origDiff =  2.22745683;

        if (savings > 49) {
            origDiff =  1.22036527;
        }

        // Calculate Pro savings.
        if (savings > 0) {
            savings = origDiff * savings;
            savingsBytes = origDiff * savingsBytes;
        }

        appLocalizer.pro_savings = {
            percent: helpers.precise_round(savings, 1),
            savings_bytes: helpers.formatBytes(savingsBytes, 1),
        };
    }

    /**
     * Start bulk Compression.
     */
    const bulkStart = function () {
        if (!appLocalizer.__is_bulk) {
            return;
        }

        // Hide the bulk div.
        jQuery('.wpfiles-bulk-wrapper').addClass('wpf-hidden');

        // Remove any global notices if there.
        jQuery('.wpf-notice-top').remove();

        // Hide the bulk limit message.
        jQuery(
            '.wpfiles-bulk-progress-bar-wrapper .wpf-notice-warning:first-of-type'
        ).hide();

        // Hide parent wrapper, if there are no other messages.
        if (
            0 >= jQuery('div.compression-final-log .compression-bulk-error-row').length
        ) {
            jQuery('div.compression-final-log').hide();
        }

        // Show the progress bar.
        jQuery(
            '.bulk-compression-wrapper .wpfiles-bulk-progress-bar-wrapper, #wpfiles-running-notice'
        ).removeClass('wpf-hidden');
    }

    /**
     * Add params to the URL.
     *
     * @param {string} url   URL to add the params to.
     * @param {Object} data  Object with params.
     * @return {string}  URL with params.
    */
    const compressionAddParams = function (url, data) {
        if (!jQuery.isEmptyObject(data)) {
            url +=
                (url.indexOf('?') >= 0 ? '&' : '?') + jQuery.param(data);
        }

        return url;
    }

    /**
     * Show bulk Compression errors, and disable bulk Compression button on completion.
     */
    const bindDeferredEvents = function () {
        const self = this;

        appLocalizer.__deferred.done(function () {
            appLocalizer.__button.removeAttr('continue_compression');

            if (appLocalizer.__errors.length) {
                /** @param {string} appLocalizer.wpfiles_msgs.error_in_bulk */
                const msg = appLocalizer.wpfiles_msgs.error_in_bulk
                    .replace('{{errors}}', appLocalizer.__errors.length)
                    .replace('{{total}}', appLocalizer.__total)
                    .replace('{{compressed}}', appLocalizer.__compressed);

                jQuery('.wpfiles-all-done')
                    .addClass('wpf-notice-warning')
                    .removeClass('wpf-notice-success')
                    .find('p')
                    .html(msg);
            }

            bulkDone();

            // Re-enable the buttons.
            jQuery(
                '.wpfiles-all:not(.wpfiles-finished), .wpfiles-scan'
            ).prop('disabled', false);
        });
    }

    const requestHeaders = function ($method) {
        if ($method == 'POST') {
            return {
                'Accept': 'application/json',
                'X-WP-Nonce': appLocalizer.nonce
            }
        }
    }

    const apiUrl = function ($action) {
        if ($action == 'wp_compressit_manual') {
            return `${appLocalizer.apiUrl}/compression/compress-one`;
        }
    }

    /**
     * Disable the action links *
     * @param c_element
     */
    const disable_links = function (c_element) {
        const parent = c_element.parent();
        //reduce parent opacity
        parent.css({ opacity: '0.5' });
        //Disable Links
        parent.find('a').prop('disabled', true);
    };

    /**
     * Enable the Action Links *
     * @param c_element
     */
    const enable_links = function (c_element) {
        const parent = c_element.parent();

        //reduce parent opacity
        parent.css({ opacity: '1' });
        //Disable Links
        parent.find('a').prop('disabled', false);
    };

    /**
     * Restore image request with a specified action for Media Library
     * @param {Object} e
     * @param {string} currentButton
     * @param {string} processAction
     * @param {string} action
     */
    const process_restore_action = function (
        e,
        currentButton,
        processAction,
        action
    ) {
        // If disabled.
        if (currentButton.prop('disabled')) {
            return;
        }

        e.preventDefault();

        // Remove Error.
        $('.wpfiles-error').remove();

        // Hide stats.
        $('.compression-stats-wrapper').hide();

        let mode = 'grid';

        if ('restore-image' === processAction) {
            if ($(document).find('div.media-modal.wp-core-ui').length > 0) {
                mode = 'grid';
            } else {
                mode = window.location.search.indexOf('item') > -1
                    ? 'grid'
                    : 'list';
            }
        }

        // Get the image ID and nonce.
        const params = {
            action: processAction,
            attachment_id: currentButton.data('id'),
            mode,
            _nonce: currentButton.data('nonce'),
        };

        // Reduce the opacity of stats and disable the click.
        disable_links(currentButton);

        currentButton.html(
            '<span class="spinner wpfiles-progress">' +
            appLocalizer.wpfiles_msgs[action] +
            '</span>'
        );

        // Restore the image.
        $.ajax({
            type: 'POST',
            data: params,
            url: `${appLocalizer.apiUrl}/compression/${processAction}`,
            headers: requestHeaders('POST'),
            /** @param {Array} appLocalizer */
            timeout: appLocalizer.timeout,
            dataType: 'json',
        }).success((r) => {
            // Reset all functionality.
            enable_links(currentButton);

            if (r.success && 'undefined' !== typeof r.data) {
                if ('restore' === action) {
                    // Show the compress button, and remove stats and restore option.
                    currentButton.parents().eq(1).html(r.data.stats);
                } else {
                    currentButton.parents().eq(1).html(r.data);
                }

                if ('undefined' !== typeof r.data && 'restore' === action) {
                    updateImageStats(r.data.new_size);
                }
            } else if (r.data && r.data.error) {
                if ('restore' === action) {
                    $('.compression-status').addClass('error').html(r.data.error);
                } else {
                    // Show error.
                    currentButton.parent().append(r.data.error);
                }
            }
        });
    };

    /** Handle compression button click **/
    $('body').on(
        'click',
        '.wpfiles-send:not(.wpfiles-recompress)',
        function (e) {
            // prevent the default action
            e.preventDefault();

            compression($(this), false);
        }
    );

    /**
     * Ignore file from bulk Compression.
     * @since 1.0.0
     */
    $('body').on('click', '.wpfiles-ignore-image', function (e) {
        e.preventDefault();
        const self = $(this);
        self.prop('disabled', true);
        self.attr('data-tooltip');
        self.removeClass('wpf-tooltip');
        $.ajax({
            type: 'POST',
            data: {
                action: 'ignore_bulk_image',
                id: self.attr('data-id'),
            },
            url: `${appLocalizer.apiUrl}/compression/ignore-bulk-image`,
            headers: requestHeaders('POST'),
            /** @param {Array} appLocalizer */
            timeout: appLocalizer.timeout,
            dataType: 'json',
        }).done((response) => {
            if (
                self.is('a') &&
                response.success &&
                'undefined' !== typeof response.data.links
            ) {
                self.parent()
                    .parent()
                    .find('.compression-status')
                    .text(appLocalizer.wpfiles_msgs.ignored);
                e.target.closest('.compression-status-links').innerHTML =
                    response.data.links;
            }
        });
    });

    /** Undo ignore image **/
    $('body').on('click', '.wpfiles-remove-skipped', function (e) {

        e.preventDefault();

        const self = $(this);

        // Send Ajax request to remove the image from the skip list.
        $.ajax({
            type: 'POST',
            data: {
                action: 'remove_from_skip_list',
                id: self.attr('data-id'),
            },
            url: `${appLocalizer.apiUrl}/compression/remove-from-skip-list`,
            headers: requestHeaders('POST'),
            /** @param {Array} appLocalizer */
            timeout: appLocalizer.timeout,
            dataType: 'json',
        }).done((response) => {
            if (
                response.success &&
                'undefined' !== typeof response.data.links
            ) {
                self.parent()
                    .parent()
                    .find('.compression-status')
                    .text(appLocalizer.wpfiles_msgs.not_processed);
                e.target.closest('.compression-status-links').innerHTML =
                    response.data.links;
            }
        });
    });

    /** Restore: Media Library **/
    $('body').on('click', '.wpfiles-action.wpfiles-restore', function (e) {
        process_restore_action(e, $(this), 'restore-image', 'restore');
    });

    /** Recompress: Media Library **/
    $('body').on('click', '.wpfiles-action.wpfiles-recompress', function (e) {
        process_restore_action(e, $(this), 'recompress-image', 'recompress');
    });

    /**
     * Handle the Compression Stats link click
     */
    $('body').on('click', 'a.compression-stats-details', function (e) {
        //If disabled
        if ($(this).prop('disabled')) {
            return false;
        }

        // prevent the default action
        e.preventDefault();
        //Replace the `+` with a `-`
        const slide_symbol = $(this).find('.stats-toggle');
        $(this).parents().eq(1).find('.compression-stats-wrapper').slideToggle();
        slide_symbol.text(slide_symbol.text() == '+' ? '-' : '+');
    });
});