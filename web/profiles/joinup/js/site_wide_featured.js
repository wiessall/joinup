/**
 * @file
 * Attaches the behaviours to mark tiles as featured site-wide.
 */
((Drupal, once) => {
  /**
   * Adds a class to featured tiles when not in scope of a group.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches site-wide featured behaviors.
   */
  Drupal.behaviors.siteWideFeatured = {
    attach(context) {
      // Create a visual cue to be added on featured content.
      const star = document.createElement("div");
      star.classList.add("listing__corner");
      star.innerHTML = "<span class='icon icon--star'></span>";
      // Check for global collection context.
      const group = context.querySelector("[data-joinup-group-context]");
      // If there is a global group context, bail out. We mark tiles as
      // globally featured only when we have no context, meaning we are in a
      // "global" page (search, collections, solutions, content).
      if (group === null || group === undefined) {
        once("featured", "[data-joinup-featured]", context).forEach(
          (element) => {
            element.classList.add("listing__card--corner");
            element.classList.add("is-featured");
            if (!element.querySelector(".listing__image")) {
              element.classList.add("listing__card--corner-title");
            }
            element.prepend(star.cloneNode(true));
          }
        );
      }
    },
  };
})(Drupal, once);
