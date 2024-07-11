/**
 * @file
 * Highlight the correct target for tour's first balloon considering user and
 * screen width.
 */
(() => {
  Drupal.behaviors.tourFirstBalloon = {
    attach(context, settings) {
      const loggedIn = settings.user.uid !== 0;
      const navbarList = document.querySelector("ul#main-navbar-list");
      let spot = {};
      if (loggedIn) {
        spot = navbarList.querySelector(
          'button[data-bs-target="#collapse-account"]',
        );
        // 992px is the breakpoint from which the menu is expanded
      } else if (window.innerWidth < 992) {
        spot = navbarList.querySelector(
          'button[data-bs-target="#collapse-navbar-toggler"]',
        );
      } else {
        const links = navbarList.querySelectorAll("li.nav-item a.nav-link");
        links.forEach((link) => {
          if (link.innerHTML.includes("Sign in")) {
            spot = link;
          }
        });
      }
      if (spot === null || spot === undefined) {
        return;
      }
      spot.setAttribute("id", "tour-first-balloon");
    },
  };
})();
