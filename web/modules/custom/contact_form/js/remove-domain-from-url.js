/**
 * @file
 * Removes the domain/base URL from a given URL input by the user if needed.
 * For sites installed in a subdirectory, it also removes the base path.
 */
((Drupal, once) => {
  Drupal.behaviors.removeDomainFromUrl = {
    attach(context, settings) {
      once(
        "remove-domain-url",
        "input#edit-field-contact-url-0-uri",
        context,
      ).forEach((input) => {
        input.addEventListener("blur", (event) => {
          const domain = window.location.origin + settings.path.baseUrl;
          if (event.target.value.startsWith(domain)) {
            event.target.value = event.target.value.replace(domain, "/");
          }
        });
      });
    },
  };
})(Drupal, once);
