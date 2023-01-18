/**
 * @file
 * Display an 'outdated content' notice if case.
 */

((Drupal, drupalSettings) => {
  /**
   * Shows a notice if the page displays an entity whose content is outdated.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Shows a notice if the page displays an entity whose content is outdated.
   */
  Drupal.behaviors.outdatedContent = {
    attach() {
      const { outdatedTime } = drupalSettings.outdatedContent;
      if (outdatedTime !== null) {
        const currentTime = Math.round(Date.now() / 1000);
        if (currentTime > outdatedTime) {
          // One year: 60 * 60 * 24 * 365 = 31536000.
          const yearsOld = Math.floor(
            (currentTime - drupalSettings.outdatedContent.publicationTime) /
              31536000
          );
          const notice = document.createElement("span");
          notice.classList.add("page-title__detail");
          const text = Drupal.formatPlural(
            yearsOld,
            "This @bundle is more than 1 year old",
            "This @bundle is more than @count years old",
            {
              "@bundle": drupalSettings.outdatedContent.bundle,
            }
          );
          notice.innerText = text;
          const titleDetails = document.querySelector(".page-title__details");
          if (titleDetails === null || titleDetails === undefined) {
            return;
          }
          if (titleDetails) {
            titleDetails.appendChild(notice);
          }
        }
      }
    },
  };
})(Drupal, drupalSettings);
