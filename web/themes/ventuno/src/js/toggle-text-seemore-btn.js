/**
 * @file
 * Toggles text inside the "See more" button in description of topic header.
 */
((Drupal, drupalSettings) => {
  Drupal.behaviors.toggleTextSeemoreBtn = {
    attach() {
      const button = document.getElementById("seeMoreBtn");
      const seeMore = Drupal.t("See more");
      const seeLess = Drupal.t("See less");
      const chevronDown = `<svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-down"></use></svg>`;
      const chevronUp = `<svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-up"></use></svg>`;
      if (button === null || button === undefined) {
        return;
      }
      button.addEventListener("click", (e) => {
        e.preventDefault();
        if (button.dataset.joinupSeemoreCollapsed === "true") {
          button.innerHTML = `${seeLess} ${chevronUp}`;
          button.dataset.joinupSeemoreCollapsed = "false";
        } else {
          button.innerHTML = `${seeMore} ${chevronDown}`;
          button.dataset.joinupSeemoreCollapsed = "true";
        }
      });
    },
  };
})(Drupal, drupalSettings);
