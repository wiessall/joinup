/**
 * @file
 * Javascript for TRR field dependencies.
 */

/**
 * Hides/shows fields on the solution form for the TRR community.
 */
(function ($) {
  Drupal.behaviors.trr_solution = {
    attach(context, settings) {
      $(context)
        .find('select[name="field_is_solution_type[]"]')
        .once("trr_solution")
        .each(function () {
          const $element = $(this);
          solution_type_updated($element);
          $element.on("change", function () {
            solution_type_updated($element);
          });
        });
    },
  };

  /**
   * Only show some field when the solution type is set to one of the TRR terms.
   *
   * @param $solution_type_element
   */
  function solution_type_updated($solution_type_element) {
    let solution_type = $solution_type_element.val();
    if (!solution_type) {
      solution_type = [];
    }
    // Test resource type field.
    const $field_is_test_resource_type = $(
      "#edit-field-is-test-resource-type-wrapper"
    );
    toggle_trr_element(solution_type, $field_is_test_resource_type);
    // Actor field.
    const $edit_field_is_actor_wrapper = $("#edit-field-is-actor-wrapper");
    toggle_trr_element(solution_type, $edit_field_is_actor_wrapper);
    // Business process field.
    const $edit_field_is_business_process_wrapper = $(
      "#edit-field-is-business-process-wrapper"
    );
    toggle_trr_element(solution_type, $edit_field_is_business_process_wrapper);
    // Business process field.
    const $edit_field_is_product_type_wrapper = $(
      "#edit-field-is-product-type-wrapper"
    );
    toggle_trr_element(solution_type, $edit_field_is_product_type_wrapper);
    // Standardisation level.
    const $edit_field_is_standardization_level_wrapper = $(
      "#edit-field-is-standardization-level-wrapper"
    );
    toggle_trr_element(
      solution_type,
      $edit_field_is_standardization_level_wrapper
    );
  }

  /**
   * Hide/show an element when the solution is a 'trr' solution.
   *
   * @param solution_type
   * @param $element
   */
  function toggle_trr_element(solution_type, $element) {
    const trr_solution_terms = [
      "http://data.europa.eu/dr8/ConformanceTestingComponent",
      "http://data.europa.eu/dr8/ConformanceTestingService",
      "http://data.europa.eu/dr8/ConformanceTestScenario",
    ];
    const selected_trr_terms = array_intersect([
      solution_type,
      trr_solution_terms,
    ]);
    if (selected_trr_terms.length) {
      $element.show();
    } else {
      $element.hide();
    }
  }

  function array_intersect(arrays) {
    return arrays.shift().reduce(function (res, v) {
      if (res.indexOf(v) === -1) {
        if (
          arrays.every(function (a) {
            return a.indexOf(v) !== -1;
          })
        ) {
          res.push(v);
        }
      }
      return res;
    }, []);
  }
})(jQuery);
