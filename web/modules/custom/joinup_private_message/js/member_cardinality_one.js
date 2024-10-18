/**
 * @file
 * Avoid adding more than one member.
 */

((Drupal, $, once) => {
  Drupal.behaviors.joinupPrivateMessageCardinalityOne = {
    attach(context) {
      $(once("cardinality-one", "input[data-drupal-selector='edit-members-target-id']", context)).each(function () {
        $(this).on("keydown", (event) => {
          if (event.key === ",") {
            event.stopPropagation();
            return false;
          }
        });
      });
    },
  };
})(Drupal, jQuery, once);
