/**
 * @file
 * Navbar hover dropdown for large devices.
 */
((Drupal, once) => {
  Drupal.behaviors.navbar_hover_dropdown = {
    attach() {
      /**
       * It toggles dropdown on hover.
       *
       * Function replicates the click functionality, but we can not use click
       * because the fist link level needs to be clickable too.
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
          top.classList.add(className);
          top.setAttribute("aria-expanded", "true");

          list.classList.add(className);
          list.setAttribute("data-bs-popper", "static");
        } else {
          top.classList.remove(className);
          top.setAttribute("aria-expanded", "false");

          list.classList.remove(className);
          list.removeAttribute("data-bs-popper");
        }
      }

      once(
        "navbar-hover-dropdown",
        "#main-navbar .nav-item.d-xl-inline-block.dropdown"
      ).forEach((li) => {
        li.addEventListener("mouseover", () => {
          toggleDropdown(li, true);
        });
        li.addEventListener("mouseleave", () => {
          toggleDropdown(li, false);
        });
      });
    },
  };
})(Drupal, once);
