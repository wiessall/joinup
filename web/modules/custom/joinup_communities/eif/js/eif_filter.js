/**
 * @file
 * Eif filter functionality.
 */

(function ($, drupalSettings) {
  "use strict";
  function checkEifCategories() {
    var $eifTile = $('.eif-tile');

    // Remove hidden class before further processing.
    $eifTile.each(function () {
      $(this).removeClass('visually-hidden');
    });

    // Check every active filter item and hide tiles which don't contain proper
    // data-eif-category.
    $('.eif-filter__item a.is-active').each(function () {
      const currentEifCategory = $(this).attr('data-eif-category');
      if (typeof currentEifCategory !== 'undefined') {
        $eifTile.each(function () {
          const eifCategory = $(this).attr('data-eif-category');
          if (typeof eifCategory !== 'undefined') {
            const eifCategoryArray = eifCategory.split(' ');
            if ($.inArray(currentEifCategory, eifCategoryArray) < 0 && !$(this).hasClass('visually-hidden')) {
              $(this).addClass('visually-hidden');
            }
          }
        });
      }
    });
  }

  // Trigger if eif filter is clicked.
  $('.eif-filter__item a').each(function () {
    $(this).on('click', function (event) {
      event.preventDefault();

      $(this).toggleClass('is-active');
      checkEifCategories();
    });
  });

  // Reset eif listing.
  $('#eif-reset').on('click', function (event) {
    $('.eif-filter__item a.is-active').removeClass('is-active');
    checkEifCategories();
  });

  // Filter on window load. Needed for eif search filter.
  $(window).on('load', function () {
    checkEifCategories();
  });

})(jQuery, drupalSettings);
