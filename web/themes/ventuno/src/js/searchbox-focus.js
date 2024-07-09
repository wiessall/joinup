/**
 * @file
 * Listen to a "click" on the searchbox trigger and focus accordingly.
 */
(() => {
  Drupal.behaviors.searchboxFocus = {
    attach() {
      const trigger = document.querySelector(
        ".joinup-navbar .dropdown-toggle#search-box-dropdown",
      );
      const box = document.querySelector("#dropdown-menu--search");
      const input = document.querySelector(
        "#dropdown-menu--search #search-box",
      );
      if (
        trigger === null ||
        trigger === undefined ||
        input === null ||
        input === undefined
      ) {
        return;
      }
      // Listen for click
      trigger.addEventListener("click", () => {
        // If click was for opening, focus.
        if (box.classList.contains("show")) {
          input.focus();
        }
        // If click was for closing, blur.
        else {
          input.blur();
        }
      });
    },
  };
})();
