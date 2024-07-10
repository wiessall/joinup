/**
 * @file
 * JavaScript code for the subscriptions form.
 */

((Drupal) => {
  Drupal.behaviors.joinupSubscription = {
    attach(context) {
      // Whenever any of the checkboxes for a collection is clicked, enable the
      // submit button.
      const frequencyButton = document
        .querySelector(
          "[data-drupal-selector='edit-field-user-frequency-wrapper']",
        )
        .querySelector("input[type='submit']");
      once(
        "user_subscriptions",
        "select[name='field_user_frequency']",
        context,
      ).forEach((selectElement) => {
        selectElement.addEventListener("change", () => {
          frequencyButton.removeAttribute("disabled");
          frequencyButton.setAttribute("value", Drupal.t("Save changes"));
          frequencyButton.classList.remove("disabled");
        });
      });
      once("user_subscriptions", "input.form-checkbox", context).forEach(
        (triggeringElement) => {
          triggeringElement.addEventListener("change", () => {
            const subscriptionStatus = [];
            const group = triggeringElement.dataset.joinupGroup;

            // Get current status of checkboxes.
            document
              .querySelectorAll(
                `input[type='checkbox'][data-joinup-group='${group}']`,
              )
              // eslint-disable-next-line max-nested-callbacks
              .forEach((checkboxElement) => {
                subscriptionStatus.push(checkboxElement.checked);
              });

            const button = document.querySelector(
              `input[type='submit'][data-joinup-group='${group}']`,
            );

            // Get initial status of checkboxes stored inside button attribute.
            const subscriptionStatusInitial =
              button.dataset.joinupSubscriptions;

            // Compare current and initial status of checkboxes.
            if (
              JSON.stringify(subscriptionStatus) === subscriptionStatusInitial
            ) {
              button.setAttribute("disabled", "disabled");
              button.classList.add("disabled");
            } else {
              button.removeAttribute("disabled");
              button.setAttribute("value", Drupal.t("Save changes"));
              button.classList.remove("disabled");
            }
          });
        },
      );
    },
  };
})(Drupal);
