/**
 * @file
 * Add overflowing nav items into a dropdown menu
 */
(($) => {
  Drupal.behaviors.ogNavigation = {
    attach(context) {
      const btn = $(".menu--og--navigation nav button.btn");
      const vlinks = $(".menu--og--navigation nav .nav");
      const hlinks = $(".menu--og--navigation nav .hidden-links");

      let numOfItems = 0;
      let totalSpace = 0;
      const breakWidths = [];

      // Get initial state
      vlinks.children().each(function (i, el) {
        const width = $(el).outerWidth();
        totalSpace += width;
        numOfItems += 1;
        breakWidths.push(totalSpace);
      });

      let availableSpace;
      let numOfVisibleItems;
      let requiredSpace;

      function checkNav() {
        // Get current state
        availableSpace = vlinks.width();
        numOfVisibleItems = vlinks.children().length;
        requiredSpace = breakWidths[numOfVisibleItems - 1];

        // Check space and move items accordingly
        if (requiredSpace > availableSpace) {
          vlinks
            .children()
            .last()
            .prependTo(hlinks)
            .children("a")
            .toggleClass("dropdown-item nav-link");
          numOfVisibleItems -= 1;
          checkNav();
        } else if (availableSpace > breakWidths[numOfVisibleItems]) {
          hlinks
            .children()
            .first()
            .appendTo(vlinks)
            .children("a")
            .toggleClass("dropdown-item nav-link");
          numOfVisibleItems += 1;
        }

        // Update the button visibility
        btn.toggleClass("d-none", numOfVisibleItems === numOfItems);
      }

      // Window resize listener
      $(window, context).on("resize", function () {
        checkNav();
      });

      // Initial check on page load
      checkNav();
    },
  };
})(jQuery);
