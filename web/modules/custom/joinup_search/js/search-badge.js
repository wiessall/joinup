/**
 * @file
 * JavaScript behaviors for the global search bar.
 */

((Drupal, once) => {
  Drupal.behaviors.globalHeaderSearch = {
    attach(context) {
      const closeIcons = once(
        "searchBadge",
        ".navbar-search .search-badge .icon--close",
        context,
      );
      closeIcons.forEach((icon) => {
        icon.addEventListener("click", () => {
          // Get data-joinup-description value to find search input, remove value and
          // achieve search accordingly.
          const badge = icon.parentNode;
          const dataDescription = badge.dataset.joinupDescription;
          const input = context.querySelector(
            `input.search_bar_badge[data-joinup-description="${dataDescription}"]`,
          );
          input.removeAttribute("value");
          badge.remove();
        });
      });
    },
  };
})(Drupal, once);
