/**
 * @file
 * Ensure sticky navbar is not hidden under Drupal admin toolbar
 */
(() => {
  Drupal.behaviors.stickyMenuLoggedIn = {
    attach() {
      const toolbar = document.getElementById("toolbar-administration");
      const navbar = document.querySelector(".navbar--sticky");
      if (
        toolbar === null ||
        toolbar === undefined ||
        navbar === null ||
        navbar === undefined
      ) {
        return;
      }
      const bar = toolbar.querySelector(".toolbar-bar");
      function positionNavbarTop() {
        // Use setTimeout to push this code to callback queue, ensuring it is
        // executed after toolbar is done with its changes.
        setTimeout(() => {
          const activeTray = toolbar.querySelector(
            ".toolbar-tray.is-active.toolbar-tray-horizontal"
          );
          if (document.body.classList.contains("toolbar-fixed")) {
            let top;
            if (activeTray === null || activeTray === undefined) {
              top = bar.offsetHeight;
            // If .region-header is also sticky push the navbar to the top. 
            } else if (document.querySelector(".region-header").classList.contains("sticky-top")) {
              top = 0;
            } else {
              top = bar.offsetHeight + activeTray.offsetHeight;
            }
            navbar.style.top = `${top}px`;
          } else {
            navbar.style.top = "0px";
          }
        }, 0);
      }
      // Execute first on page load.
      positionNavbarTop();
      // Execute again if user clicks on any toolbar item.
      toolbar.addEventListener("click", positionNavbarTop);
      // Execute again if user resizes the window.
      window.addEventListener("resize", positionNavbarTop, false);
      // Execute again if user scrolls.
      window.addEventListener("scroll", positionNavbarTop, false);
    },
  };
})();
