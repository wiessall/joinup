(function (Drupal) {
  Drupal.behaviors.disableCKEditorButton = {
    attach(context, settings) {
      // Wait for CKEditor to be ready.
      window.onload = function () {
        // Find all textareas with attribute data-editor-active-text-format="content_editor"
        const textareas = document.querySelectorAll(
          'textarea[data-editor-active-text-format="content_editor"]'
        );

        textareas.forEach(function (textarea) {
          // Find the next sibling element which should be the div containing the buttons
          const divElement = textarea.nextElementSibling;

          if (divElement) {
            // Find all buttons with attribute data-cke-tooltip-text="Insert image" within the div
            const buttons = divElement.querySelectorAll(
              'button[data-cke-tooltip-text="Insert image"]'
            );

            buttons.forEach(function (button) {
              // Remove the button
              button.parentNode.removeChild(button);
            });
          }
        });
      };
    },
  };
})(Drupal);
