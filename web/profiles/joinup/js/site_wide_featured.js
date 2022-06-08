/**
 * @file
 * Attaches the behaviours to mark tiles as featured site-wide.
 */
((Drupal, once) => {
  /**
   * Theme function that renders a visual cue for featured content.
   *
   * @return {string}
   *   The HTML for the featured content visual cue.
   */
  Drupal.theme.featuredContentCue = () => {
    return '<div class="listing__corner"><span class="icon icon--star"></span></div>';
  };
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
      const group = context
        .querySelector("[data-drupal-group-context]")
        .data("drupal-group-context");
      // If there is a global group context, bail out. We mark tiles as
      // globally featured only when we have no context, meaning we are in a
      // "global" page (search, collections, solutions, content).
      if (group) {
        return;
      }
      once("featured", "[data-drupal-featured]", context).forEach((element) => {
        element.classList.add("is-featured listing__card--corner");
        if (!element.querySelector(".listing__image")) {
          element.classList.add("listing__card--corner-title");
        }
        element.insertBefore(
          Drupal.theme("featuredContentCue"),
          element.firstChild
        );
      });
    },
  };
})(Drupal, once);
