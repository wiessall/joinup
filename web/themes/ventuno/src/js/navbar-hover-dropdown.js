/**
 * Navbar hover dropdown.
 */
(function (Drupal, once) {
  Drupal.behaviors.navbar_hover_dropdown = {
    attach: function () {
      /**
       * It toggles dropdown on hover.
       *
       * Function replicates the click functionality, but we can not use click
       * because the fist link level needs to be clickable too.
       *
       * @param {HTMLElement} li
       *   List element.
       * @param expand
       *   True if we want to expand.
       */
      function toggleDropdown(li, expand) {
        let className = 'show';
        let a = li.querySelector('a.dropdown-toggle');
        let list = li.querySelector('ul.dropdown-menu');

        if (expand) {
          a.classList.add(className);
          list.classList.add(className);

          a.setAttribute('aria-expanded', 'true');
          list.setAttribute('data-bs-popper', 'static');
        }
        else {
          a.classList.remove(className);
          list.classList.remove(className);

          a.setAttribute('aria-expanded', 'false');
          list.removeAttribute('data-bs-popper');
        }
      }

      once('navbar-hover-dropdown', '#main-navbar .nav-item.d-xl-inline-block.dropdown')
          .forEach(function (li) {
            li.addEventListener('mouseover', () => {
              toggleDropdown(li, true);
            });
            li.addEventListener("mouseleave", () => {
              toggleDropdown(li, false);
            });
          })
    }
  };
})(Drupal, once);
