/**
 * @file
 * Javascript for TRR field dependencies.
 */

/**
 * Hides/shows fields on the solution form for the TRR community.
 */
(($) => {
  /**
   * Computes the intersection of two or many array.
   *
   * @param {Array[]} arrays
   *   An array of arrays.
   * @return {*}
   *   The intersection of the passed arrays.
   */
  function arrayIntersect(arrays) {
    return arrays.shift().reduce((res, v) => {
      if (res.indexOf(v) === -1) {
        if (
          arrays.every((a) => {
            return a.indexOf(v) !== -1;
          })
        ) {
          res.push(v);
        }
      }
      return res;
    }, []);
  }

  /**
   * Hide/show an element when the solution is a 'trr' solution.
   *
   * @param {String[]} solutionType
   *   The solution type.
   * @param {HTMLElement} $element
   *   The element.
   */
  function toggleTrrElement(solutionType, $element) {
    const trrSolutionTerms = [
      "http://data.europa.eu/dr8/ConformanceTestingComponent",
      "http://data.europa.eu/dr8/ConformanceTestingService",
      "http://data.europa.eu/dr8/ConformanceTestScenario",
    ];
    const selectedTrrTerms = arrayIntersect([solutionType, trrSolutionTerms]);
    if (selectedTrrTerms.length) {
      $element.show();
    } else {
      $element.hide();
    }
  }

  /**
   * Only show some field when the solution type is set to one of the TRR terms.
   *
   * @param {HTMLElement} $solutionTypeElement
   *   The element.
   */
  function solutionTypeUpdated($solutionTypeElement) {
    let solutionType = $solutionTypeElement.val();
    if (!solutionType) {
      solutionType = [];
    }
    // Test resource type field.
    const $fieldIsTestResourceType = $(
      "#edit-field-is-test-resource-type-wrapper"
    );
    toggleTrrElement(solutionType, $fieldIsTestResourceType);
    // Actor field.
    const $editFieldIsActorWrapper = $("#edit-field-is-actor-wrapper");
    toggleTrrElement(solutionType, $editFieldIsActorWrapper);
    // Business process field.
    const $editFieldIsBusinessProcessWrapper = $(
      "#edit-field-is-business-process-wrapper"
    );
    toggleTrrElement(solutionType, $editFieldIsBusinessProcessWrapper);
    // Business process field.
    const $editFieldIsProductTypeWrapper = $(
      "#edit-field-is-product-type-wrapper"
    );
    toggleTrrElement(solutionType, $editFieldIsProductTypeWrapper);
    // Standardisation level.
    const $editFieldIsStandardizationLevelWrapper = $(
      "#edit-field-is-standardization-level-wrapper"
    );
    toggleTrrElement(solutionType, $editFieldIsStandardizationLevelWrapper);
  }
  Drupal.behaviors.trr_solution = {
    attach(context) {
      $(context)
        .find('select[name="field_is_solution_type[]"]')
        .once("trr_solution")
        .each(() => {
          const $element = $(this);
          solutionTypeUpdated($element);
          $element.on("change", () => {
            solutionTypeUpdated($element);
          });
        });
    },
  };
})(jQuery);
