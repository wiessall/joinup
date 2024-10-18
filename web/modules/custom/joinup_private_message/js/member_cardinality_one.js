/**
 * @file
 * Avoid adding more than one member.
 */

((Drupal, once) => {
  Drupal.behaviors.joinupPrivateMessageCardinalityOne = {
    attach(context) {
      once("cardinality-one", "input[data-drupal-selector='edit-members-target-id']", context).forEach(function () {
        this.addEventListener("keydown", (event) => {
          if (event.key === ",") {
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);
