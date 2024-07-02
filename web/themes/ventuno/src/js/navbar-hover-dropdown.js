/**
 * @file
 * Navbar hover dropdown for large devices.
 */
((Drupal, once) => {
  Drupal.behaviors.navbar_hover_dropdown = {
    attach() {
      /**
       * Variable to store the timeout ID.
       */
      let mouseLeaveTimeout;

      /**
       * It toggles dropdown on hover.
       *
       * Function replicates the click functionality, but we cannot use click
       * because the first link level needs to be clickable too.
       *
       * @param {HTMLElement} li
       *   List element.
       * @param {boolean} expand
       *   True if we want to expand.
       */
      function toggleDropdown(li, expand) {
        const className = "show";
        const top = li.querySelector("a.dropdown-toggle, span.dropdown-toggle");
        const list = li.querySelector("ul.dropdown-menu");

        if (expand) {
          clearTimeout(mouseLeaveTimeout); // Clear any existing timeout
          top.classList.add(className);
          top.setAttribute("aria-expanded", "true");
          list.classList.add(className);
          list.setAttribute("data-bs-popper", "static");
        } else {
          // Set a short delay before hiding the dropdown
          mouseLeaveTimeout = setTimeout(() => {
            top.classList.remove(className);
            top.setAttribute("aria-expanded", "false");
            list.classList.remove(className);
            list.removeAttribute("data-bs-popper");
          }, 100); // Set timeout delay.
        }
      }

      /**
       * Adds event listeners to the list item.
       *
       * @param {Array<string>} events - List of events to listen for.
       * @param {HTMLElement} li - List element.
       * @param {boolean} expand - True if we want to expand.
       */
      function addEventListeners(events, li, expand) {
        events.forEach((event) => {
          li.addEventListener(event, () => toggleDropdown(li, expand));
        });
      }

      once(
        "navbar-hover-dropdown",
        "#main-navbar .nav-item.d-xl-inline-block.dropdown"
      ).forEach((li) => {
        addEventListeners(["mouseover", "focusin"], li, true);
        addEventListeners(["mouseleave", "focusout"], li, false);
      });
    },
  };
})(Drupal, once);
