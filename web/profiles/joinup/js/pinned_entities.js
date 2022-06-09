/**
 * @file
 * Attaches the behaviours to show visual cues on pinned community content.
 */
((Drupal, once) => {
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
      // Create a visual cue to be added on pinned content.
      const pin = document.createElement("div");
      pin.classList.add("listing__corner");
      pin.innerHTML = "<span class='icon icon--pin'></span>";
      // Check for global collection context.
      const group = context.querySelector("[data-drupal-group-context]").dataset
        .drupalGroupContext;
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
        // This is needed for listing title padding if image doesn't exist.
        if (!element.querySelector(".listing__image")) {
          element.classlist.add("listing__card--corner-title");
        }
        // Add the visual where needed.
        element.prepend(pin.cloneNode(true));
      });
    },
  };
})(Drupal, once);
