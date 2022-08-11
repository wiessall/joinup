/**
 * @file
 * Eif module behaviors.
 */
((Drupal) => {
  Drupal.behaviors.Eif = {
    attach(context) {
      const // Get the select element.
        recommendationSelector = context.querySelector(
          'select[data-drupal-eif-recommendation-selector="true"]'
        );
      // Get the parent form.
      const form = recommendationSelector.closest("form");
      // Bind the event handler.
      recommendationSelector.addEventListener("change", () => {
        if (recommendationSelector.value !== "") {
          form.submit();
        }
      });
    },
  };
})(Drupal);
