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
      once("track-download", "a.track-download", context).forEach(
        (downloadLink) => {
          const url = downloadLink.dataset.joinupTracking;
          const base = downloadLink.id;
          if (
            url === null ||
            url === undefined ||
            base === null ||
            base === undefined
          ) {
            return;
          }
          downloadLink.addEventListener("click", () => {
            Drupal.ajax({
              url,
              base,
              progress: false,
              element: downloadLink,
              dialogType: "modal",
            }).execute();
            // IE11 misbehaves when clicking the link and the default behaviour
            // is not working. For IE11, force the click. The following check
            // ensures that this is IE and fails for Edge (where the click event
            // behaves properly).
            // @see https://stackoverflow.com/a/21825207.
            if (!!window.MSInputMethodContext && !!document.documentMode) {
              window.location.href = downloadLink.href;
            }
          });
        }
      );
    },
  };
})(Drupal, once);
