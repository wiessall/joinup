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
      const toolbarBar = toolbar.querySelector(".toolbar-bar");
      function calculateTranslation() {
        setTimeout(() => {
          const toolbarTrayActive = toolbar.querySelector(
            ".toolbar-tray.is-active.toolbar-tray-horizontal"
          );
          let translation;
          if (toolbarTrayActive === null || toolbarTrayActive === undefined) {
            translation = toolbarBar.offsetHeight;
          } else {
            translation =
              toolbarBar.offsetHeight + toolbarTrayActive.offsetHeight;
          }
          if (document.body.classList.contains("toolbar-fixed")) {
            navbar.style.top = `${translation}px`;
          } else {
            navbar.style.top = "0px";
          }
        }, 0);
      }
      calculateTranslation();
      toolbar.addEventListener("click", calculateTranslation);
      window.addEventListener("resize", calculateTranslation, false);
    },
  };
})();
