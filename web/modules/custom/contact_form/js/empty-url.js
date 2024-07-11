/**
 * @file
 * Empties the "url" input if user selects categories other than bug or report.
 */
(() => {
  Drupal.behaviors.emptyUrlContactForm = {
    attach() {
      const url = document.querySelector("input[name*='field_contact_url']");
      const category = document.querySelector(
        "select[name='field_contact_category']",
      );
      if (
        url === null ||
        url === undefined ||
        category === null ||
        category === undefined
      ) {
        return;
      }
      category.addEventListener("change", (event) => {
        if (!["bug", "report"].includes(event.target.value)) {
          url.value = "";
        }
      });
    },
  };
})();
