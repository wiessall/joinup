/**
 * @file
 * Remove the domain from a given URL inputed by the user if needed.
 * Intended for the contact form (but can be extended to other cases).
 */
 (() => {

  Drupal.behaviors.removeDomainFromUrl = {
    attach: function () {
      const
        domain = window.location.origin,
        input = document.querySelector('input#edit-field-contact-url-0-uri');
      if (input === null || input === undefined) {
        return
      }
      input.addEventListener('blur', (event) => {
        if (event.target.value.startsWith(domain)) {
          event.target.value = event.target.value.replace(domain, '');
        }
      });
    }
  }

})();
