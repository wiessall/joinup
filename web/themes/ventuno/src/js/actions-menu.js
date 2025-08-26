/**
 * @file
 * Force dropdowns with class .js-responsive-dropdown placement depending on screen size, no flipping.
 */

((Drupal, once) => {
  Drupal.behaviors.dropdownPlacement = {
    attach(context) {
      once("dropdown-placement", ".js-responsive-dropdown", context).forEach(
        (trigger) => {
          // Create new instance with forced placement and no flipping.
          const instance = bootstrap.Dropdown.getInstance(trigger);
          if (instance) {
            instance.dispose();
          }

          // eslint-disable-next-line no-new
          new bootstrap.Dropdown(trigger, {
            popperConfig: () => {
              // Same as Bootstrap media-breakpoint-up(lg).
              const isLargeScreen = window.innerWidth >= 992;
              return {
                placement: isLargeScreen ? "bottom-end" : "top-end",
                modifiers: [
                  // Completely disable flipping.
                  { name: "flip", enabled: false },
                  // Optional: prevents repositioning.
                  { name: "preventOverflow", enabled: false },
                ],
              };
            },
          });
        },
      );
    },
  };
})(Drupal, once);
