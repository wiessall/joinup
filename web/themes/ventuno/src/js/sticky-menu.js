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
                navbar.classList.add("js-is--sticky");
                // Since the navbar is removed of the document flow, add it's
                // height as padding-top to the body so there is no visual
                // shift in the content below.
                document.body.style.paddingTop = `${navbar.offsetHeight}px`;
              } else {
                navbar.classList.remove("js-is--sticky");
                document.body.style.removeProperty("padding-top");
              }
            });
          }
        },
        { once: true }
      );
    },
  };
})();
