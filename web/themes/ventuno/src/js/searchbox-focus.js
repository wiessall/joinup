/**
 * @file
 * Listen to a "click" on the searchbox trigger and focus accordingly.
 */
(() => {
  Drupal.behaviors.searchboxFocus = {
    attach() {
      const trigger = document.querySelector(
        "#joinup-navbar .dropdown-toggle#search"
      );
      const box = document.querySelector("#dropdown-menu--search");
      const input = document.querySelector("#dropdown-menu--search #edit-keys");
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
        // If click was for closing, blur.
        if (!box.classList.contains("show")) {
          input.blur();
        }
        // If click was for opening, focus.
        else {
          input.focus();
        }
      });
    },
  };
})();
