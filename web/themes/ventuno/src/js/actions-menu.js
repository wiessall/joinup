/**
 * @file
 * Customize popper.js behavior for actions menu dropdowns.
 */

((Drupal, once) => {
  Drupal.behaviors.actionsMenu = {
    attach(context) {
      once(
        "actions-menu-dropdown",
        ".js-actions-menu-dropdown",
        context,
      ).forEach((trigger) => {
        // Create new instance with forced placement and no flipping.
        const instance = bootstrap.Dropdown.getInstance(trigger);
        if (instance) {
          instance.dispose();
        }

        // eslint-disable-next-line no-new
        new bootstrap.Dropdown(trigger, {
          popperConfig: () => {
            // Same as Bootstrap media-breakpoint-up(lg).
            // Get Bootstrap `lg` breakpoint value from CSS variables.
            const lgBreakpoint = getComputedStyle(document.body)
              .getPropertyValue("--bs-breakpoint-lg")
              .trim();
            // Convert e.g. "992px" to 992 (fallback 992 if parsing fails).
            const bp = parseInt(lgBreakpoint, 10) || 992;
            const isLargeScreen = window.innerWidth >= bp;

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
      });
    },
  };
})(Drupal, once);
