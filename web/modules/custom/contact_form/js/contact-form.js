/**
 * @param Drupal
 * @param once
 * @file
 * JS for the contact form.
 */
((Drupal, once) => {
  Drupal.behaviors.contactForm = {
    attach(context, settings) {
      once(
        "contact-form",
        ".message-contact-form-submission-form",
        context,
      ).forEach((form) => {
        // Remove the base URL and base path from the URL input.
        const urlInput = form.querySelector("input[name*='field_contact_url']");
        urlInput.addEventListener("blur", (event) => {
          const domain = window.location.origin + settings.path.baseUrl;
          if (event.target.value.startsWith(domain)) {
            event.target.value = event.target.value.replace(domain, "/");
          }
        });

        // Clear URL input if user selects categories other than bug or report.
        const categorySelect = form.querySelector(
          "select[name='field_contact_category']",
        );
        categorySelect.addEventListener("change", ({ target }) => {
          if (!["bug", "report"].includes(target.value)) {
            urlInput.value = "";
          }
        });
      });
    },
  };
})(Drupal, once);
