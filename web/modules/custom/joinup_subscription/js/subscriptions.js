/**
 * @file
 * JavaScript code for the subscriptions form.
 */

(function ($, Drupal) {
  Drupal.behaviors.joinupSubscription = {
    attach(context, settings) {
      // Whenever any of the checkboxes for a collection is clicked, enable the
      // submit button.
      $(context)
        .find(".form-select")
        .each(function () {
          $(this).on("change", function () {
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
        .each(function () {
          $(this).on("click", function () {
            const subscription_status = [];

            // Get current status of checkboxes.
            $(this)
              .closest(".form__subscribe-types-inner")
              .find("input.form-checkbox")
              .each(function () {
                subscription_status.push($(this).prop("checked"));
              });

            // Get subscription button.
            const $button = $(this)
              .closest(".form__subscribe-types-inner")
              .find("input.button");

            // Get initial status of checkboxes stored inside button attribute.
            const subscription_status_initial = $button.attr(
              "data-drupal-subscriptions"
            );

            // Compare current and initial status of checkboxes.
            if (
              JSON.stringify(subscription_status) ===
              subscription_status_initial
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
