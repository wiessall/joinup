/**
 * @file
 * Licence compatibility checker functionality.
 */

((drupalSettings) => {
  Drupal.behaviors.licenceCompatibilityCheck = {
    attach() {
      // Enable or disable all compare buttons.
      function enableButtons(state) {
        if (state) {
          const buttons = document.querySelectorAll(".jla-compatible");
          buttons.forEach((element) => {
            if (element.classList.contains("disabled")) {
              element.classList.remove("disabled");
              element.setAttribute("aria-disabled", "false");
            }
          });
        } else {
          document.querySelectorAll(".jla-compatible").forEach((element) => {
            element.classList.add("disabled");
            element.setAttribute("aria-disabled", "true");
          });
        }
      }

      // Reset licence listing.
      const licenceResetButton = document.getElementById("licence-reset");

      licenceResetButton.addEventListener("click", () => {
        // Also, uncheck any selected licences checked for comparison and disable
        // the "Compare" buttons. The 'data-joinup-licence-compare' attribute which is set
        // to [], is the attribute that stores the licences set-up for comparison.
        document
          .querySelectorAll(".licence-tile .form-check-input[type='radio']")
          .forEach((el) => {
            el.checked = false;
          });
        document.querySelectorAll("a.jla-compatible").forEach((el) => {
          el.classList.add("disabled");
          el.ariaDisabled = true;
        });
        document
          .querySelector(".listing--licences")
          .setAttribute("data-joinup-licence-compare", "[]");
      });

      function initCompatibleRadios() {
        const radios = document.querySelectorAll(
          ".jla--compatibility .form-check-input[type='radio']",
        );
        radios.forEach((element) => {
          // Uncheck radios on page load.
          element.checked = false;
          element.addEventListener("click", () => {
            const inboundLicence = document.querySelector(
              "input[name='inbound-licence']:checked",
            );
            const distributeLicence = document.querySelector(
              "input[name='outbound-licence']:checked",
            );
            if (inboundLicence && distributeLicence) {
              const compatibleUrl = `${
                drupalSettings.path.baseUrl
              }licence/compatibility-check/${inboundLicence.getAttribute(
                "data-joinup-licence-name",
              )}/${distributeLicence.getAttribute("data-joinup-licence-name")}`;
              const licenceTileCompatibleButton =
                document.querySelectorAll(".jla-compatible");
              // eslint-disable-next-line max-nested-callbacks
              licenceTileCompatibleButton.forEach((el) => {
                el.setAttribute("href", compatibleUrl);
              });
              enableButtons(true);
            }
          });
        });
      }
      initCompatibleRadios();
    },
  };
})(drupalSettings);
