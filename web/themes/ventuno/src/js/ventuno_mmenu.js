/**
 * @file
 * Ventuno mobile menu.
 */

(function ($, Drupal, once) {

  Drupal.behaviors.ventuno_mmenu = {
    attach(context) {
      console.log('attached');
      once('ventuno-mmenu','.joinup-navbar .dropdown-menu[data-bs-popper]', context).forEach(function (nav) {
        console.log('once');
        const mmenu = new window.MmenuLight(nav);
        mmenu.navigation({
          'title': Drupal.t('Main menu'),
        });
        const drawer = mmenu.offcanvas();

        $('.joinup-navbar .dropdown .dropdown-toggle[data-bs-toggle="dropdown"]').on('click', function (e) {
          console.log('click');
          e.preventDefault();
          e.stopImmediatePropagation();
          drawer.open();
        });
      });
    }
  }
})(jQuery, Drupal, once);
