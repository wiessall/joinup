/**
 * @file
 * Highlight the correct target for tour's first balloon considering user and
 * screen width.
 */
 Drupal.behaviors.tourFirstBalloon = {
  attach: function (context, settings) {
    const
      loggedIn = (settings.user.uid != 0) ? true : false,
      navbarList = document.querySelector('ul#main-navbar-list');
    let spot = {};
    if (loggedIn) {
      spot =
        navbarList.querySelector('button[data-bs-target="#collapse-account"]');
    }
    else {
    // 992px is the breakpoint from which the menu is expanded
      if (window.innerWidth < 992) {
        spot =
          navbarList.querySelector('button[data-bs-target="#collapse-navbar-toggler"]');
      }
      else {
        const links = navbarList.querySelectorAll('li.nav-item a.nav-link');
        links.forEach((link) => {
          if (link.innerHTML.includes('Sign in')) {
            spot = link;
          }
        });
      }
    }
    if (spot === null || spot === undefined) {
      return;
    }
    else {
      spot.setAttribute('id', 'tour-first-balloon');
    }
  }
};
