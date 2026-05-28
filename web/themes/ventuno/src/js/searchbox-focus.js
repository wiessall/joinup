/**
 * @param Drupal
 * @param once
 * @file
 * Focus on the search box input when opening the dropdown.
 */
((Drupal, once) => {
  Drupal.behaviors.searchboxFocus = {
    attach(context) {
      once(
        "search-box-focus",
        ".joinup-navbar .dropdown-search",
        context,
      ).forEach((dropdown) => {
        const toggle = dropdown.querySelector(
          ".dropdown-toggle#search-box-dropdown",
        );
        const input = dropdown.querySelector(
          ".dropdown-menu#dropdown-menu--search #search-box",
        );
        if (!toggle || !input) {
          return;
        }

        toggle.addEventListener("shown.bs.dropdown", () => input.focus());
        toggle.addEventListener("hidden.bs.dropdown", () => input.blur());
      });
    },
  };
})(Drupal, once);
