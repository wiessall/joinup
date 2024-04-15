/**
 * @file
 * Add overflowing nav items into a dropdown menu
 */
((Drupal, once, priorityPlus) => {
  Drupal.behaviors.ogNavigation = {
    attach(context) {
      once("og-navigation", ".menu--og--navigation .nav", context).forEach(
        (nav) => {
          // @see options: https://github.com/jayfreestone/priority-plus?tab=readme-ov-file#options
          priorityPlus(nav, {
            innerToggleTemplate: () => Drupal.t("See more"),
            classNames: {
              "overflow-nav": ["p-plus__overflow", "nav"],
              "toggle-btn": ["p-plus__toggle-btn", "btn", "btn-link"],
            },
          });
        }
      );
    },
  };
})(Drupal, once, window.priorityPlus);
