/**
 * @param Drupal
 * @param once
 * @file
 * Open accordions to allow user spot the invalid required field(s).
 */
((Drupal, once) => {
  Drupal.behaviors.showAccordionInvalidFields = {
    attach(context) {
      // Function to open the accordion item
      function openAccordionItem(element) {
        const item = element.closest(".accordion-item");
        const button = item.querySelector(".accordion-button");
        if (button.classList.contains("collapsed")) {
          button.click();
        }
      }

      // Use once to target the required fields within the context
      once(
        "show-accordion-invalid-fields",
        ".accordion-body .required",
        context,
      ).forEach((field) => {
        field.addEventListener("invalid", () => {
          openAccordionItem(field);
        });
      });
    },
  };
})(Drupal, once);
