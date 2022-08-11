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
          const navbar = document.getElementById("stickynav");
          if (navbar === null || navbar === undefined) {
            return;
          }
          if (navbar) {
            const navbarOffsetTop = navbar.getBoundingClientRect().top;
            // Run function on user scroll.
            window.addEventListener("scroll", () => {
              if (window.pageYOffset > navbarOffsetTop) {
                navbar.classList.add("js-is--sticky");
                // Add the required padding top to show content behind navbar.
                document.body.style.paddingTop = `${navbar.offsetHeight}px`;
              } else {
                navbar.classList.remove("js-is--sticky");
                // Remove padding top from body.
                document.body.style.paddingTop = "0";
              }
            });
          }
        },
        { once: true }
      );
    },
  };
})();
