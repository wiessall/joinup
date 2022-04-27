/**
 * @file
 * Open accordions to allow user spot the invalid required input(s).
 */
(() => {

  Drupal.behaviors.fieldGroupTabsValidation = {
    attach: function () {
      const requiredForms = document.querySelectorAll('.accordion-body .required');
      requiredForms.forEach((form) => {
        form.addEventListener('invalid', () => {
          openAccordionItem(form);
          if (!form.classList.contains('is-invalid')) {
            form.classList.add('is-invalid');
          };
        });
      });
      function openAccordionItem(element) {
        const item = element.closest('.accordion-item');
        const button = item.querySelector('.accordion-button');
        if (button.classList.contains('collapsed')) {
          button.click();
        }
      }
    }
  }

})();
