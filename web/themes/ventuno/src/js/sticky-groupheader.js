/**
 * @file
 * Add a sticky-top css class on groupheader when srolling to the top.
 */
(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.addStickyClass = {
    attach: function (context) {
      $(window, context).scroll(function () {
        // Push this code to callback queue.
        setTimeout(() => {
          var header = $(".region-header", context);
          var toTop = header.offset().top;
          if (header.length) {
            header.toggleClass("sticky-top shadow", $(this).scrollTop() >= toTop);
          }
        }, 100);
      });
    }
  };

})(jQuery, Drupal);


