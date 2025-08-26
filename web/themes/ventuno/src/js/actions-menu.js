/**
 * @file
 * Force dropdowns with class .js-responsive-dropdown placement depending on screen size, no flipping.
 */

((Drupal, once) => {
  Drupal.behaviors.dropdownPlacement = {
    attach(context) {
      const triggers = once(
        'dropdown-placement',
        '.js-responsive-dropdown',
        context
      );

      if (!triggers.length) return;

      function updateDropdownPlacement() {
        triggers.forEach(trigger => {
          const isLargeScreen = window.innerWidth >= 992; // Same as Bootstrap media-breakpoint-up(lg)

          // Destroy existing dropdown instance (if any).
          const existing = bootstrap.Dropdown.getInstance(trigger);
          if (existing) {
            existing.dispose();
          }

          // Create new instance with forced placement and no flipping.
          bootstrap.Dropdown.getOrCreateInstance(trigger, {
            popperConfig: () => {
              return {
                placement: isLargeScreen ? 'bottom-end' : 'top-end',
                modifiers: [
                  {
                    name: 'flip',
                    enabled: false, // completely disable flipping
                  },
                  {
                    name: 'preventOverflow',
                    enabled: false, // optional: prevents repositioning
                  },
                ],
              };
            },
          });
        });
      }

      // Run once on load.
      updateDropdownPlacement();

      // Reapply on resize.
      let resizeTimeout;
      window.addEventListener('resize', () => {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(updateDropdownPlacement, 150);
      });
    },
  };
})(Drupal, once);
