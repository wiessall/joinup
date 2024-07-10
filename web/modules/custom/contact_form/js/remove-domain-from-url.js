/**
 * @file
 * Removes the domain from a given URL inputed by the user if needed.
 * For the sites that are installed in a sub-directory, it also removes the base
 * path (drupalSettings.path.baseUrl).
 */
((drupalSettings) => {
  Drupal.behaviors.removeDomainFromUrl = {
    attach() {
      const input = document.querySelector(
        "input#edit-field-contact-url-0-uri",
      );
      if (input === null || input === undefined) {
        return;
      }
      input.addEventListener("blur", (event) => {
        const domain = window.location.origin + drupalSettings.path.baseUrl;
        if (event.target.value.startsWith(domain)) {
          event.target.value = event.target.value.replace(domain, "/");
        }
      });
    },
  };
})(drupalSettings);
