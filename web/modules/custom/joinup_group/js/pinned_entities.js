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
      pin.classList.add("mark");
      pin.classList.add("pin");
      // Check for global collection context.
      const group = context.querySelector("[data-joinup-group-context]");
      // If there is no global collection context, bail out.
      if (group === null || group === undefined) {
        return;
      }
      const groupContext = group.dataset.joinupGroupContext;
      once("pinned-cue", ".is-pinned", context).forEach((element) => {
        const parent = element.dataset.joinupParentId;
        const affiliated = element.dataset.joinupPinnedIn || "";
        // Show the cue only when the global collection context is the same
        // as the parent of the tile.
        if (
          parent !== groupContext &&
          affiliated.split(",").indexOf(groupContext) === -1
        ) {
          return;
        }
        // Add the visual where needed.
        element.prepend(pin.cloneNode(true));
      });
    },
  };
})(Drupal, once);
