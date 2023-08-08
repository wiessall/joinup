(function (Drupal) {
  Drupal.behaviors.disableCKEditorButton = {
    attach(context, settings) {
      // Wait for CKEditor to be ready.
      window.onload = function () {
        // Find all button elements with the specified attribute
        const buttonElements = document.querySelectorAll(
          'button[data-cke-tooltip-text="Insert image"]'
        );

        // Loop through each button element
        buttonElements.forEach(function (buttonElement) {
          // Find the parent element with the specified class
          const parentElement = buttonElement.closest(".ck-file-dialog-button");

          if (parentElement) {
            // Remove the parent element
            parentElement.parentNode.removeChild(parentElement);
          }
        });
      };
    },
  };
})(Drupal);
