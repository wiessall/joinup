/**
 * @file
 * Toggles text inside the "See more" buttons.
 */

((Drupal) => {
  Drupal.behaviors.toggleTextSeemoreBtn = {
    attach(context) {
      const buttons = context.querySelectorAll(".js-see-more-btn");
      if (buttons.length === 0) {
        return;
      }

      const seeMore = `${Drupal.t("See more")} <svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-down"></use></svg>`;
      const seeLess = `${Drupal.t("See less")} <svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-up"></use></svg>`;
      buttons.forEach((button) => {
        const collapse = context.querySelector(
          button.getAttribute("data-bs-target"),
        );
        if (!collapse) {
          return;
        }

        collapse.addEventListener("hidden.bs.collapse", () => {
          button.innerHTML = seeMore;
        });
        collapse.addEventListener("shown.bs.collapse", () => {
          button.innerHTML = seeLess;
        });
      });
    },
  };
})(Drupal, drupalSettings);
