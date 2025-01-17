/**
 * @file
 * Exposes an 'outdated content' notice if case.
 */

((Drupal, once) => {
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
    attach(context, settings) {
      // Get all page-title__details elements in the current context.
      once("outdated-content", ".page-title__details", context).forEach(
        (titleDetails) => {
          const { outdatedContent } = settings;
          if (
            outdatedContent !== null &&
            outdatedContent.outdatedTime !== null
          ) {
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

              // Create and append the outdated content notice.
              const displayedNotice = document.createElement("span");
              displayedNotice.classList.add("page-title__detail");
              displayedNotice.innerText = text;
              titleDetails.appendChild(displayedNotice);
            }
          }
        },
      );
    },
  };
})(Drupal, once);
