/**
 * @file
 * Attaches the behaviors for the distribution download tracking module.
 */

((Drupal, once) => {
  /**
   * Tracks downloads of distributions.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches distribution download tracking functionality.
   */
  Drupal.behaviors.distributionDownloadTracking = {
    attach(context) {
      once("track-download", "a.track-download", context).forEach((link) => {
        const url = link.dataset.joinupTracking;
        const base = link.id;
        if (!url || !base) {
          return;
        }

        link.addEventListener("click", () => {
          Drupal.ajax({
            url,
            base,
            element: link,
            dialogType: "modal",
            dialog: { autoResize: true, width: "auto" },
          }).execute();
        });
      });
    },
  };
})(Drupal, once);
