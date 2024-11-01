/* global compression_vars */
/* global _ */

/**
 * Adds a Compress Now button and displays stats in Media Attachment Details Screen
 */
 (function ($, _) {
    'use strict';

    // Local reference to the WordPress media namespace.
    const WPFiles = wp.media,
        sharedTemplate =
            "<span class='setting compression-stats' data-setting='compression'>" +
            "<span class='name'><%= label %></span>" +
            "<span class='value'><%= value %></span>" +
            '</span>',
        template = _.template(sharedTemplate);

    /**
     * Create the template.
     *
     * @param {string} compressHTML
     * @return {Object} Template object
     */
    const prepareTemplate = function (compressHTML) {
        /**
         * @param {Array}  compression_vars.strings  Localization strings.
         * @param {Object} compression_vars          Object from wp_localize_script()
         */
        return template({
            label: compression_vars.strings.stats_label,
            value: compressHTML,
        });
    };

    if (
        'undefined' !== typeof WPFiles.view &&
        'undefined' !== typeof WPFiles.view.Attachment.Details.TwoColumn
    ) {
        // Local instance of the Attachment Details TwoColumn used in the edit attachment modal view
        const WpFilesTwoColumn =
            WPFiles.view.Attachment.Details.TwoColumn;

        /**
         * Add Compression details to attachment.
         *
         * A similar view to media.view.Attachment.Details
         * for use in the Edit Attachment modal.
         *
         * @see wp-includes/js/media-grid.js
         */
        WPFiles.view.Attachment.Details.TwoColumn = WpFilesTwoColumn.extend(
            {
                initialize() {
                    WpFilesTwoColumn.prototype.initialize.apply(this, arguments);
                    this.listenTo(this.model, 'change:wpfiles', this.render);
                },

                render() {
                    // Ensure that the main attachment fields are rendered.
                    WPFiles.view.Attachment.prototype.render.apply(
                        this,
                        arguments
                    );

                    const compressHTML = this.model.get('compress');
                    if (typeof compressHTML === 'undefined') {
                        return this;
                    }

                    this.model.fetch();

                    /**
                     * Detach the views, append our custom fields, make sure that our data is fully updated
                     * and re-render the updated view.
                     */
                    this.views.detach();
                    this.$el
                        .find('.settings')
                        .append(prepareTemplate(compressHTML));
                    this.views.render();

                    return this;
                },
            }
        );
    }

    // Local instance of the Attachment Details TwoColumn used in the edit attachment modal view
    const WpFilesAttachmentDetails = WPFiles.view.Attachment.Details;

    /**
     * Add Compression details to attachment.
     */
    WPFiles.view.Attachment.Details = WpFilesAttachmentDetails.extend({
        initialize() {
            WpFilesAttachmentDetails.prototype.initialize.apply(this, arguments);
            this.listenTo(this.model, 'change:wpfiles', this.render);
        },

        render() {
            // Ensure that the main attachment fields are rendered.
            WPFiles.view.Attachment.prototype.render.apply(this, arguments);

            const compressHTML = this.model.get('wpfiles');
            if (typeof compressHTML === 'undefined') {
                return this;
            }

            this.model.fetch();

            /**
             * Detach the views, append our custom fields, make sure that our data is fully updated
             * and re-render the updated view.
             */
            this.views.detach();
            this.$el.append(prepareTemplate(compressHTML));

            return this;
        },
    });

    /**
     * Create a new MediaLibraryTaxonomyFilter we later will instantiate
     *
     * @since 3.0
     */
    const MediaLibraryTaxonomyFilter = wp.media.view.AttachmentFilters.extend({
        id: 'media-attachment-compression-filter',

        createFilters() {
            this.filters = {
                all: {
                    text: compression_vars.strings.filter_all,
                    props: { stats: 'all' },
                    priority: 10,
                },

                uncompressed: {
                    text: compression_vars.strings.filter_not_processed,
                    props: { stats: 'uncompressed' },
                    priority: 20,
                },

                excluded: {
                    text: compression_vars.strings.filter_excl,
                    props: { stats: 'excluded' },
                    priority: 30,
                },
            };
        },
    });

    /**
     * Extend and override wp.media.view.AttachmentsBrowser to include our new filter.
     * @since 1.0
     */
    const AttachmentsBrowser = wp.media.view.AttachmentsBrowser;
    wp.media.view.AttachmentsBrowser = wp.media.view.AttachmentsBrowser.extend({
        createToolbar() {
            // Make sure to load the original toolbar
            AttachmentsBrowser.prototype.createToolbar.call(this);
            this.toolbar.set(
                'MediaLibraryTaxonomyFilter',
                new MediaLibraryTaxonomyFilter({
                    controller: this.controller,
                    model: this.collection.props,
                    priority: -75,
                }).render()
            );
        },
    });
})(jQuery, _);
