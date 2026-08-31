/**
 * @param Drupal
 * @param once
 * @file
 * Highlight the correct target for tour's first balloon considering user and
 * screen width.
 */
((Drupal, once) => {
  Drupal.behaviors.tourFirstBalloon = {
    attach(context, settings) {
      const navbarList = once(
        "tour-first-balloon",
        "ul#main-navbar-list",
        context,
      ).shift();
      if (!navbarList) return;

      const loggedIn = settings.user.uid !== 0;

      // Determine first balloon based on user login status. The sign in link is
      // shown on every breakpoint, so it is the anonymous target throughout.
      const spot = loggedIn
        ? navbarList.querySelector("button#account")
        : navbarList.querySelector("li.nav-item .sign-in-link");
      if (!spot) return;
      spot.classList.add("tour-first-balloon");
    },
  };
})(Drupal, once);
