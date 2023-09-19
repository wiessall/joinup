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
      star.classList.add("mark");
      star.classList.add("star");

      once("featured", "[data-joinup-featured]", context).forEach((element) => {
        element.prepend(star.cloneNode(true));
      });
    },
  };
})(Drupal, once);
