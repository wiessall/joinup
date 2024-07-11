/**
 * @file
 * Exposes an 'outdated content' notice if case.
 */

((Drupal, drupalSettings) => {
  /**
   * Makes a notice available in the window object if the page displays an
   * entity whose content is outdated so that the theme layer can render it.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches outdated content behaviors.
   */
  Drupal.behaviors.outdatedContent = {
    attach() {
      const { outdatedContent } = drupalSettings;
      if (outdatedContent !== null && outdatedContent.outdatedTime !== null) {
        const currentTime = Math.round(Date.now() / 1000);
        if (currentTime > outdatedContent.outdatedTime) {
          // One year: 60 * 60 * 24 * 365 = 31536000.
          const yearsOld = Math.floor(
            (currentTime - outdatedContent.publicationTime) / 31536000,
          );
          const text = Drupal.formatPlural(
            yearsOld,
            "This @bundle is more than 1 year old",
            "This @bundle is more than @count years old",
            {
              "@bundle": outdatedContent.bundle,
            },
          );

          const titleDetails = document.querySelector(".page-title__details");
          if (titleDetails !== null || titleDetails !== undefined) {
            const displayedNotice = document.createElement("span");
            displayedNotice.classList.add("page-title__detail");
            displayedNotice.innerText = text;
            if (displayedNotice) {
              titleDetails.appendChild(displayedNotice);
            }
          }
        }
      }
    },
  };
})(Drupal, drupalSettings);
