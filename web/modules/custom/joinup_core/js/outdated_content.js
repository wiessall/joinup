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
      const { outdatedTime } = drupalSettings.outdatedContent;
      if (outdatedTime !== null) {
        const currentTime = Math.round(Date.now() / 1000);
        if (currentTime > outdatedTime) {
          // One year: 60 * 60 * 24 * 365 = 31536000.
          const yearsOld = Math.floor(
            (currentTime - drupalSettings.outdatedContent.publicationTime) /
              31536000
          );
          const text = Drupal.formatPlural(
            yearsOld,
            "This @bundle is more than 1 year old",
            "This @bundle is more than @count years old",
            {
              "@bundle": drupalSettings.outdatedContent.bundle,
            }
          );
          drupalSettings.outdatedContent.notice = text;
        }
      }
    },
  };
})(Drupal, drupalSettings);
