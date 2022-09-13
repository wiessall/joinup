/**
 * @file
 * Listen to DOM readiness, look for navbar element and if present, listen to
 * user scroll to toggle sticky classes.
 */
(() => {
  Drupal.behaviors.stickyMenu = {
    attach() {
      // Make sure Webtools is done modifying the DOM.
      window.addEventListener(
        "wtGlobanReady",
        () => {
          const navbar = document.querySelector("nav.stickynav");
          if (navbar === null || navbar === undefined) {
            return;
          }
          if (navbar) {
            const navbarOffsetTop = navbar.getBoundingClientRect().top;
            // Run function on user scroll.
            window.addEventListener("scroll", () => {
              if (window.pageYOffset > navbarOffsetTop) {
                if (!navbar.classList.contains("js-is--sticky")) {
                  navbar.classList.add("js-is--sticky");
                }
                if (
                  document.body.style.paddingTop !== `${navbar.offsetHeight}px`
                ) {
                  // Add the required padding top to show content behind navbar.
                  document.body.style.paddingTop = `${navbar.offsetHeight}px`;
                }
              } else {
                if (navbar.classList.contains("js-is--sticky")) {
                  navbar.classList.remove("js-is--sticky");
                }
                if (document.body.style.paddingTop) {
                  // Remove padding top from body.
                  document.body.style.removeProperty("padding-top");
                }
              }
            });
          }
        },
        { once: true }
      );
    },
  };
})();
