/**
 * @file
 * JavaScript behaviors for the global search bar.
 */

(function ($, Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.globalHeaderSearch = {
    attach: function (context, settings) {
      $('.navbar-search .badge .icon--close', context).once('myBadge').each(function () {
        this.addEventListener('click', function () {
          let name = $(this).parent().data('description');
          $(".search_bar_badge[data-description='" + name + "']").val('');
          $(this).parent().remove();
        })
      });
    }
  };

})(jQuery, Drupal, drupalSettings);
