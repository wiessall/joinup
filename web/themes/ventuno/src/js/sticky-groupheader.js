/**
 * @file
 * Make groupheader sticky when scrolling to the top.
 */
(function ($, Drupal) {
  "use strict";

  Drupal.behaviors.addStickyClass = {
    attach: function (context) {
      // Define variables outside the scroll event
      var header = $(".region-header", context),
          toolbar = $(".toolbar-fixed .toolbar-oriented .toolbar-bar"),
          joinupNavbar = $(".joinup-navbar"),
          toTop = header.offset().top;

      // Function to add/remove sticky class based on scroll position
      function toggleStickyClass() {
        var scrollTop = $(window).scrollTop();

        if (scrollTop >= toTop) {
          header.addClass("sticky-top shadow");
        } else {
          header.removeClass("sticky-top shadow");
        }
      }

      // Call the function if #block-ventuno-groupheader exists
      if ($('#block-ventuno-groupheader').length) {
        // Call the function on page load
        toggleStickyClass();

        // Call the function on scroll
        $(window, context).scroll(function () {
          toggleStickyClass();
        });

        // Remove stickyness to admin toolbar and main navigation
        toolbar.addClass("position-static");
        $("body").css("padding-top", "0");
        joinupNavbar.removeClass("navbar--sticky").addClass("position-static");

        // Update body padding-top if user clicks on any toolbar item
        toolbar.on("click", function () {
          setTimeout(() => {
            $("body").css("padding-top", "0");
          }, 20);
        });
      }
    }
  };

})(jQuery, Drupal);
