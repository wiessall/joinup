/**
 * @file
 * JavaScript code for the subscriptions form.
 */

(($, Drupal) => {
  Drupal.behaviors.joinupSubscription = {
    attach(context) {
      // Whenever any of the checkboxes for a collection is clicked, enable the
      // submit button.
      $(context)
        .find(".form-select")
        .each(() => {
          $(this).on("change", () => {
            $(this)
              .closest(".field--name-field-user-frequency")
              .find("input.button")
              .prop("disabled", false)
              .prop("value", "Save changes")
              .removeClass("is-disabled");
          });
        });
      $(context)
        .find("input.form-checkbox")
        .once("dashboard")
        .each(() => {
          $(this).on("click", () => {
            const subscriptionStatus = [];

            // Get current status of checkboxes.
            $(this)
              .closest(".form__subscribe-types-inner")
              .find("input.form-checkbox")
              .each(() => {
                subscriptionStatus.push($(this).prop("checked"));
              });

            // Get subscription button.
            const $button = $(this)
              .closest(".form__subscribe-types-inner")
              .find("input.button");

            // Get initial status of checkboxes stored inside button attribute.
            const subscriptionStatusInitial = $button.attr(
              "data-drupal-subscriptions"
            );

            // Compare current and initial status of checkboxes.
            if (
              JSON.stringify(subscriptionStatus) ===
              subscriptionStatusInitial
            ) {
              $button.prop("disabled", true).addClass("is-disabled");
            } else {
              $button
                .prop("disabled", false)
                .prop("value", "Save changes")
                .removeClass("is-disabled");
            }
          });
        });
    },
  };
})(jQuery, Drupal);
