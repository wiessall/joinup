/**
 * @file
 * Eif module behaviors.
 */
(() => {
  Drupal.behaviors.eifRecommendations = {
    attach(context) {
      // Get the select element.
      const recommendationSelector = context.querySelector(
        'select[data-joinup-eif-recommendation-selector="true"]'
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
})();
