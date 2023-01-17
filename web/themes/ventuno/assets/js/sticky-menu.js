(function (factory) {
  typeof define === 'function' && define.amd ? define(factory) :
  factory();
})((function () { 'use strict';

  /**
   * @file
   * Listen to DOM readiness, look for navbar element and if present, listen to
   * user scroll to toggle sticky classes.
   * @todo ISAICP-7138 delete file when switch to Ventuno theme is complete
   */
  (() => {
    Drupal.behaviors.stickyMenu = {
      attach() {
        // Make sure Webtools is done modifying the DOM.
        window.addEventListener("wtGlobanReady", () => {
          const navbar = document.querySelector("nav.stickynav");
          if (navbar === null || navbar === undefined) {
            return;
          }
          if (navbar) {
            const navbarOffsetTop = navbar.getBoundingClientRect().top;
            const placeholder = document.createElement("div");
            placeholder.setAttribute("aria-hidden", true);
            placeholder.style.height = `${navbar.offsetHeight}px`;
            // Run function on user scroll.
            window.addEventListener("scroll", () => {
              if (window.pageYOffset > navbarOffsetTop) {
                navbar.classList.add("js-is--sticky");
                // Since the navbar is removed of the document flow, add a
                // placeholder just before it to prevent a visual shift in the
                // content below.
                navbar.before(placeholder);
              } else {
                navbar.classList.remove("js-is--sticky");
                // Remove the placeholder since navbar is back in document flow
                placeholder.remove();
              }
            });
          }
        }, {
          once: true
        });
      }
    };
  })();

}));
//# sourceMappingURL=sticky-menu.js.map
