/**
 * @file
 * Ventuno mobile menu.
 */

((Drupal, once) => {
  Drupal.behaviors.mmenuNav = {
    attach(context) {
      once("mmenuNav", ".mmenu-nav", context).forEach((navbar) => {
        const menu = navbar.querySelector(":scope > .mmenu-nav__menu");
        const button = navbar.querySelector(":scope > .mmenu-nav__toggle");
        if (!menu || !button) {
          return;
        }

        // Apply mmenu.
        const mmenu = new window.MmenuLight(menu);
        mmenu.navigation({});
        menu.classList.remove("d-none");

        // Create the drawer and open/close click listener.
        const drawer = mmenu.offcanvas();
        button.addEventListener("click", (e) => {
          e.preventDefault();
          e.stopImmediatePropagation();
          drawer.open();
        });
      });
    },
  };
})(Drupal, once);
