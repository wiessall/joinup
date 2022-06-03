/**
 * @file
 * Popovers.
 */
Drupal.behaviors.popover = {
  attach() {
    const popoverTriggerList = [].slice.call(
      document.querySelectorAll('[data-bs-toggle="popover"]')
    );
    popoverTriggerList.map((popoverTriggerEl) => {
      return new bootstrap.Popover(popoverTriggerEl);
    });
  },
};
