/**
 * @file
 * Toggles text inside the "See more" button in topic header, Collections and Solutions description field.
 */
((Drupal, drupalSettings) => {
  Drupal.behaviors.toggleTextSeemoreBtn = {
    attach() {
      const collapseSeeMore = document.getElementById("collapseSeeMore");
      const button = document.getElementById("seeMoreBtn");
      const seeMore = Drupal.t("See more");
      const seeLess = Drupal.t("See less");
      const chevronDown = `<svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-down"></use></svg>`;
      const chevronUp = `<svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#chevron-up"></use></svg>`;

      if (button) {
        collapseSeeMore.addEventListener("hidden.bs.collapse", () => {
          button.innerHTML = `${seeMore} ${chevronDown}`;
        });

        collapseSeeMore.addEventListener("shown.bs.collapse", () => {
          button.innerHTML = `${seeLess} ${chevronUp}`;
        });
      }
    },
  };
})(Drupal, drupalSettings);
