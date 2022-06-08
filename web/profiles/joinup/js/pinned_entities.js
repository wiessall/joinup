/**
 * @file
 * Attaches the behaviours to show visual cues on pinned community content.
 */
((Drupal, once) => {
  /**
   * Theme function that renders a visual cue for pinned content.
   *
   * @return {string}
   *   The HTML for the pinned content visual cue.
   */
  Drupal.theme.pinnedContentCue = () => {
    return '<div class="listing__corner"><span class="icon icon--pin"></span></div>';
  };
  /**
   * Adds visual cues for content pinned inside the parent collection.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches pinned entities behaviors.
   */
  Drupal.behaviors.pinnedEntities = {
    attach(context) {
      const group = context
        .querySelector("[data-drupal-group-context]")
        .data("drupal-group-context");
      // If there is no global collection context, bail out.
      if (!group) {
        return;
      }
      once("pinned-cue", ".is-pinned", context).forEach((element) => {
        const parent = element.dataset.drupalParentId;
        const affiliated = element.dataset.drupalPinnedIn || "";
        // Show the cue only when the global collection context is the same
        // as the parent of the tile.
        if (parent !== group && affiliated.split(",").indexOf(group) === -1) {
          return;
        }
        element.classList.add("listing__card--corner");
        // It is needed for listing title padding if image doesn't exist.
        if (!element.querySelector(".listing__image")) {
          element.classlist.add("listing__card--corner-title");
        }
        element.insertBefore(
          Drupal.theme("pinnedContentCue"),
          element.firstChild
        );
      });
    },
  };
})(Drupal, once);
