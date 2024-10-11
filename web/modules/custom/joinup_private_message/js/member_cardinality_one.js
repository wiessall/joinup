/**
 * @file
 * Avoid adding more than one member.
 */

((Drupal, $) => {
  Drupal.behaviors.cardinalityOne = {
    attach() {
      const $input = $("input[data-drupal-selector='edit-members-target-id']");
      $input.on("keydown", (event) => {
        if (event.key === ",") {
          event.stopPropagation();
          return false;
        }
      });
    },
  };
})(Drupal, jQuery);
