/**
 * @file
 * Avoid adding more than one member.
 */

((Drupal, once) => {
  Drupal.behaviors.joinupPrivateMessageCardinalityOne = {
    attach(context) {
      once(
        "cardinality-one",
        "input.js-joinup-private-message-cardinality",
        context,
      ).forEach(function () {
        this.addEventListener("keydown", (event) => {
          // A colon is used to separate members. If we restrict this character,
          // the user will not be able to add more members and our goal here is met.
          if (event.key === ",") {
            event.preventDefault();
          }
        });
      });
    },
  };
})(Drupal, once);
