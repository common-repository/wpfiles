;(function ($) {
    'use strict'
  
    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     *
     * Note: It has been assumed you will write jQuery code here, so the
     * $ function reference has been prepared for usage within the scope
     * of this function.
     *
     * This enables you to define handlers, for when the DOM is ready:
     *
     * $(function() {
     *
     * });
     *
     * When the window is loaded:
     *
     * $( window ).load(function() {
     *
     * });
     *
     * ...and/or other possibilities.
     *
     * Ideally, it is not considered best practise to attach more than a
     * single DOM-ready or window-load handler for a particular page.
     * Although scripts in the WordPress core, Plugins and Themes may be
     * practising this, we should strive to set a better example in our own work.
     */
    //WPFiles screens
    if (
      appLocalizer?.screen === 'upload.php' ||
      appLocalizer?.screen === 'wpfiles_page_wpfiles-modules' ||
      appLocalizer?.screen === 'toplevel_page_wpfiles' ||
      appLocalizer?.screen === 'wpfiles_page_wpfiles-settings' ||
      appLocalizer?.screen === 'wpfiles_page_wpfiles-pro'
    ) {
      $(
        '<div class="wp-load-more initial-load"><svg class="spinit" viewBox="0 0 50 50"><circle class="path" cx="25" cy="25" r="20" fill="none" stroke-width="4"></circle></svg></div>'
      ).insertBefore('#wpbody')
  
      $(window).load(function () {
        $('.uploader-window').remove();
      })
  
        $('.update-nag').hide();
  
      //Hide all notices except WPFiles
      $('#wpbody-content .wpfiles-notice, .notice').each(function () {
        if (!$(this).hasClass('wpfiles-notice')) {
          $(this).remove()
        }
      });
    }
  
    /********Notices********/
  
    const remove_element = function (el, timeout) {
      if (typeof timeout === 'undefined') {
        timeout = 100
      }
      el.fadeTo(timeout, 0, function () {
        el.slideUp(timeout, function () {
          el.remove()
        })
      })
    }
  
    //Handle Re-check button functionality
    $(document).on('click', '#wpfiles-revalidate-member', (e) => {
      e.preventDefault()
      const params = {
        action: 'update_api_status',
      }
      $.get(ajaxurl, params, function (r) {
        if (r.success) {
          window.location.href = appLocalizer.admin_url+'?page=wpfiles&action=api_update';
        }
      })
    });
  
    $(document).on('click', '#wpfiles-plugin-conflict-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-plugin-conflict-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-plugin-conflict-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-account-connect-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-account-connect-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-account-connect-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-cdn-suspended-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-cdn-suspended-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-cdn-suspended-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-initial-trial-upgrade-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-initial-trial-upgrade-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-initial-trial-upgrade-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
    
    $(document).on('click', '#wpfiles-account-payment-due-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-account-payment-due-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-account-payment-due-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-upgrade-to-pro-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-upgrade-to-pro-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-upgrade-to-pro-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-dismiss-usage-tracking-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-dismiss-usage-tracking-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-dismiss-usage-tracking-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-toggle-usage-tracking-content', (e) => {
      if (
        $(
          '#wpfiles-dismiss-usage-tracking-notice-parent .wf-permissions'
        ).hasClass('wf-permissions--show')
      ) {
        $(
          '#wpfiles-dismiss-usage-tracking-notice-parent .wf-permissions'
        ).removeClass('wf-permissions--show')
      } else {
        $(
          '#wpfiles-dismiss-usage-tracking-notice-parent .wf-permissions'
        ).addClass('wf-permissions--show')
      }
    })
  
    $(document).on('click', '#wpfiles-usage-tracking_submit', (e) => {
      e.preventDefault()
      const params = {
        action: 'save_usage_tracking',
      }
      const parent = $('#wpfiles-dismiss-usage-tracking-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    });
  
    $(document).on('click', '#wpfiles-account-domain-mismatch-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-account-domain-mismatch-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-account-domain-mismatch-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-rate-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-rate-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-rate-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    });
  
    $(document).on('click', '#wpfiles-rate-notice-already-done', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-rate-notice-already-done').attr('data-notice'),
      }
      const parent = $('#wpfiles-rate-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    });
  
    $(document).on('click', '#wpfiles-free-to-pro-plugin-conversion-notice', (e) => {
      e.preventDefault()
      const params = {
        delete: true,
        action: 'dismiss_notice',
        notice: $('#wpfiles-free-to-pro-plugin-conversion-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-free-to-pro-plugin-conversion-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
  
    $(document).on('click', '#wpfiles-website-pro-to-free-notice', (e) => {
      e.preventDefault()
      const params = {
        action: 'dismiss_notice',
        notice: $('#wpfiles-website-pro-to-free-notice').attr('data-notice'),
      }
      const parent = $('#wpfiles-website-pro-to-free-notice-parent')
      parent.addClass('loading-notice')
      $.post(ajaxurl, params, function (r) {
        parent.removeClass('loading-notice').addClass('loaded-notice')
        remove_element(parent, 200)
      })
    })
    
    $(document).on('click', '.connect-account', (e) => {
      window.open(
        `${appLocalizer.wpfiles_app_url}/user/dashboard?action=add-website&redirect=${window.location.href}`,
        '_blank'
      )
    });
  
  })(jQuery)
  