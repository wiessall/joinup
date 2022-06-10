/**
 * @file
 * JavaScript behaviors for the global search bar.
 */

((Drupal, once) => {
  Drupal.behaviors.globalHeaderSearch = {
    attach(context) {
      const closeIcons = once(
        "myBadge",
        ".navbar-search .badge .icon--close",
        context
      );
      closeIcons.forEach((icon) => {
        icon.addEventListener("click", () => {
          // Get data-description value to remove it from search input and
          // achieve search accordingly.
          const badge = icon.parentNode;
          const dataDescription = badge.dataset.description;
          const input = context.querySelector(
            `input.search_bar_badge[data-description="${dataDescription}"]`
          );
          input.removeAttribute("data-description");
          badge.remove();
        });
      });
    },
  };
})(Drupal, once);
