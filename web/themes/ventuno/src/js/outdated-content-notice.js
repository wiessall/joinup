/**
 * @file
 * Renders an 'outdated content' notice if case.
 */

((Drupal, drupalSettings) => {
  /**
   * Picks the notice available in the window object if the page displays an
   * entity whose content is outdated and renders it.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches outdated content notice behaviors.
   */
  Drupal.behaviors.outdatedContentNotice = {
    attach() {
      const { notice } = drupalSettings.outdatedContent;
      if (notice === null || notice === undefined) {
        return
      }
      if (notice) {
        const displayedNotice = document.createElement("span");
        displayedNotice.classList.add("page-title__detail");
        displayedNotice.innerText = notice;
        const titleDetails = document.querySelector(".page-title__details");
        if (titleDetails === null || titleDetails === undefined) {
          return;
        }
        if (titleDetails) {
          titleDetails.appendChild(displayedNotice);
        }
      }
    },
  };
})(Drupal, drupalSettings);
