/**
 * @file
 * Highlight the invalid required field(s) adding corresponding class.
 */
(() => {
  Drupal.behaviors.highlightInvalidFields = {
    attach() {
      const requiredFields = document.querySelectorAll(".required");
      requiredFields.forEach((field) => {
        field.addEventListener("invalid", () => {
          if (!field.classList.contains("is-invalid")) {
            field.classList.add("is-invalid");
          }
        });
      });
    },
  };
})();
