/**
 * @param Drupal
 * @param once
 * @param drupalSettings
 * @file
 * Toggles text inside the "See more" buttons.
 */

((Drupal, once, drupalSettings) => {
  Drupal.behaviors.toggleTextSeemoreBtn = {
    attach(context) {
      once("toggle-text-see-more", ".js-see-more-btn", context).forEach(
        (button) => {
          const collapse = context.querySelector(
            button.getAttribute("data-bs-target"),
          );
          if (!collapse) {
            return;
          }

          collapse.addEventListener("hidden.bs.collapse", () => {
            button.innerHTML = `${Drupal.t("See more")} <svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-down"></use></svg>`;
          });
          collapse.addEventListener("shown.bs.collapse", () => {
            button.innerHTML = `${Drupal.t("See less")} <svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-up"></use></svg>`;
          });
        },
      );
    },
  };
})(Drupal, once, drupalSettings);
