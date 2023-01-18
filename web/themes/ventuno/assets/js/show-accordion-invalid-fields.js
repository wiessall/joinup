(function (factory) {
  typeof define === 'function' && define.amd ? define(factory) :
  factory();
})((function () { 'use strict';

  /**
   * @file
   * Open accordions to allow user spot the invalid required field(s).
   */
  (() => {
    Drupal.behaviors.showAccordionInvalidFields = {
      attach() {
        function openAccordionItem(element) {
          const item = element.closest(".accordion-item");
          const button = item.querySelector(".accordion-button");
          if (button.classList.contains("collapsed")) {
            button.click();
          }
        }
        const requiredFields = document.querySelectorAll(".accordion-body .required");
        requiredFields.forEach(field => {
          field.addEventListener("invalid", () => {
            openAccordionItem(field);
          });
        });
      }
    };
  })();

}));
//# sourceMappingURL=show-accordion-invalid-fields.js.map
