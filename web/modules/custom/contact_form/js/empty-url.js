/**
 * @file
 * Empties the "url" input if user selects categories other than bug or report.
 */
((Drupal, once) => {
  Drupal.behaviors.emptyUrlContactForm = {
    attach(context) {
      once(
        "empty-url-contact-form",
        "#message-contact-form-submission-form",
        context,
      ).forEach((form) => {
        const urlInput = form.querySelector("input[name*='field_contact_url']");
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
