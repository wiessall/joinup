/**
 * @file
 * Navbar hover dropdown for large devices.
 */
((Drupal, once) => {
  Drupal.behaviors.navbar_hover_dropdown = {
    attach() {
      /**
       * Toggles the given dropdown on mouse hover / keyboard focus.
       */
      class NavbarHoverDropdown {
        constructor(dropdown) {
          this.dropdown = dropdown;
          this.mouseLeaveTimeout = null;
          this.init();
        }

        init() {
          ["mouseover", "focusin"].forEach((evtName) => {
            this.dropdown.addEventListener(evtName, () =>
              this.toggleDropdown(true),
            );
          });
          ["mouseleave", "focusout"].forEach((evtName) => {
            this.dropdown.addEventListener(evtName, () =>
              this.toggleDropdown(false),
            );
          });
        }

        toggleDropdown(expand) {
          const className = "show";
          const toggle = this.dropdown.querySelector(
            ":scope > .dropdown-toggle",
          );
          const menu = this.dropdown.querySelector(":scope > .dropdown-menu");

          if (expand) {
            // Clear any existing timeout.
            clearTimeout(this.mouseLeaveTimeout);
            toggle.classList.add(className);
            toggle.setAttribute("aria-expanded", "true");
            menu.classList.add(className);
            menu.setAttribute("data-bs-popper", "static");
          } else {
            // Set timeout delay before closing.
            this.mouseLeaveTimeout = setTimeout(() => {
              toggle.classList.remove(className);
              toggle.setAttribute("aria-expanded", "false");
              menu.classList.remove(className);
              menu.removeAttribute("data-bs-popper");
            }, 100);
          }
        }
      }

      once(
        "navbar-hover-dropdown",
        "#main-navbar .nav-item.d-xl-inline-block.dropdown",
      ).forEach((li) => new NavbarHoverDropdown(li));
    },
  };
})(Drupal, once);
