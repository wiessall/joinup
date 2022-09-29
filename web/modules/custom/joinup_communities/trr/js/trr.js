/**
 * @file
 * Javascript for TRR field dependencies.
 */

/**
 * Hides/shows fields on the solution form for the TRR community.
 */
(() => {
  Drupal.trrSolution = {
    solutionTerms: [
      "http://data.europa.eu/dr8/ConformanceTestingComponent",
      "http://data.europa.eu/dr8/ConformanceTestingService",
      "http://data.europa.eu/dr8/ConformanceTestScenario",
    ],

    fieldsSelector:
      "#edit-field-is-test-resource-type-wrapper,#edit-field-is-actor-wrapper,#edit-field-is-business-process-wrapper,#edit-field-is-product-type-wrapper,#edit-field-is-standardization-level-wrapper",

    /**
     * Only show some field when the solution type is set to one of the TRR terms.
     *
     * @param {HTMLSelectElement} solutionTypeElement
     *   The element.
     */
    solutionTypeUpdated: (solutionTypeElement) => {
      const solutionTypes = [];
      solutionTypeElement.querySelectorAll("option:checked").forEach((o) => {
        solutionTypes.push(o.getAttribute("value"));
      });

      const selectedTrrTerms = solutionTypes.filter((item) =>
        Drupal.trrSolution.solutionTerms.includes(item)
      );
      document
        .querySelectorAll(Drupal.trrSolution.fieldsSelector)
        .forEach((field) => {
          if (selectedTrrTerms.length) {
            field.classList.remove("visually-hidden");
          } else {
            field.classList.add("visually-hidden");
          }
        });
    },
  };

  /**
   * Only clear some fields when the solution type is changed.
   */
  const clearFields = () => {
    document
      .querySelectorAll(Drupal.trrSolution.fieldsSelector)
      .forEach((field) => {
        const element = field.querySelector("input, select");
        if (element.type === "text") {
          element.value = "";
        } else {
          element.value = "_none";
        }
      });
  };

  Drupal.behaviors.trrSolution = {
    attach(context) {
      once(
        "trr_solution",
        "select[name='field_is_solution_type[]']",
        context
      ).forEach((solutionTypeElement) => {
        Drupal.trrSolution.solutionTypeUpdated(solutionTypeElement);
        solutionTypeElement.addEventListener("change", () => {
          Drupal.trrSolution.solutionTypeUpdated(solutionTypeElement);
          clearFields();
        });
      });
    },
  };
})();
