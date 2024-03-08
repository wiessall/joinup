/**
 * @file
 * Make groupheader sticky when scrolling to the top.
 */
(function ($, Drupal) {
  Drupal.behaviors.addStickyClass = {
    attach(context) {
      // Define variables outside the scroll event
      const header = $(".region-header", context);
      const toolbar = $(".toolbar-fixed .toolbar-oriented .toolbar-bar");
      const joinupNavbar = $(".joinup-navbar");
      const toTop = header.offset().top;

      // Function to add/remove sticky class based on scroll position
      function toggleStickyClass() {
        const scrollTop = $(window).scrollTop();

        if (scrollTop >= toTop) {
          header.addClass("sticky-top shadow");
        } else {
          header.removeClass("sticky-top shadow");
        }
      }

      // Call the function if #block-ventuno-groupheader exists
      if ($("#block-ventuno-groupheader").length) {
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
    },
  };
})(jQuery, Drupal);
