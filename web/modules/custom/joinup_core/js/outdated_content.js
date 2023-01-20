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
            (currentTime - outdatedContent.publicationTime) / 31536000
          );
          const text = Drupal.formatPlural(
            yearsOld,
            "This @bundle is more than 1 year old",
            "This @bundle is more than @count years old",
            {
              "@bundle": outdatedContent.bundle,
            }
          );

          let displayedNotice;

          // TODO: Remove .page__title-wrapper .details.after-title in
          // ISAICP-7138. See
          // https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7138
          const titleDetailsJoinup = document.querySelector(
            ".page__title-wrapper .details.after-title"
          );
          if (titleDetailsJoinup !== null || titleDetailsJoinup !== undefined) {
            displayedNotice = document.createElement("div");
            displayedNotice.classList.add("details__element");
            displayedNotice.classList.add("outdated-content-notice");
            displayedNotice.innerText = text;
          }

          const titleDetailsVentuno = document.querySelector(
            ".page-title__details"
          );
          if (
            titleDetailsVentuno !== null ||
            titleDetailsVentuno !== undefined
          ) {
            displayedNotice = document.createElement("span");
            displayedNotice.classList.add("page-title__detail");
            displayedNotice.innerText = text;
          }

          const titleDetails = titleDetailsJoinup || titleDetailsVentuno;
          if (titleDetails && displayedNotice) {
            titleDetails.appendChild(displayedNotice);
          }
        }
      }
    },
  };
})(Drupal, drupalSettings);
