/**
 * @file
 * Remove the domain from a given URL inputed by the user if needed.
 */
(() => {
  Drupal.behaviors.removeDomainFromUrl = {
    attach() {
      const domain = window.location.origin;
      const input = document.querySelector(
        "input#edit-field-contact-url-0-uri"
      );
      if (input === null || input === undefined) {
        return;
      }
      input.addEventListener("blur", (event) => {
        if (event.target.value.startsWith(domain)) {
          event.target.value = event.target.value.replace(domain, "");
        }
      });
    },
  };
})();
