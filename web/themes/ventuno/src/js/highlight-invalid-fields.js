/**
 * @file
 * Highlight the invalid required field(s) adding corresponding class.
 */
((Drupal, once) => {
  Drupal.behaviors.highlightInvalidFields = {
    attach(context) {
      // Limit the query to only elements within the context to prevent adding listeners multiple times
      once("highlight-invalid-fields", ".required", context).forEach(
        (field) => {
          field.addEventListener("invalid", () => {
            if (!field.classList.contains("is-invalid")) {
              field.classList.add("is-invalid");
            }
          });
        },
      );
    },
  };
})(Drupal, once);
