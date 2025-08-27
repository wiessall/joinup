/**
 * @file
 * Customize popper.js behavior for actions menu dropdowns.
 *
 * Force dropdowns to open above on lg screens and up; otherwise, open below.
 * See also web/themes/ventuno/src/scss/components/actions-menu.scss
 */

((Drupal, once) => {
  Drupal.behaviors.actionsMenu = {
    attach(context) {
      const isBreakpointUp = (breakpoint) => {
        const value = getComputedStyle(
          document.documentElement,
        ).getPropertyValue(`--bs-breakpoint-${breakpoint}`);
        if (!value) return false;
        return window.matchMedia(`(min-width: ${value})`).matches;
      };

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
            return {
              placement: isBreakpointUp("lg") ? "bottom-end" : "top-end",
              modifiers: [
                // Disable flipping.
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
