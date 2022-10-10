/**
 * @file
 * Licence compatibility checker functionality.
 */

((drupalSettings) => {
  Drupal.behaviors.licenceCompatibilityCheck = {
    attach() {
      // function checkLicenceCategories() {
      //   const licenceTile = document.querySelectorAll(".licence-tile");

      // Remove hidden class before further processing.
      // licenceTile.forEach((el) => {
      //   if (el.classList.contains("is-hidden")) {
      //     el.classList.remove("is-hidden");
      //   }
      // });

      // Check licence search field value and hide tiles which don't contain
      // proper data-joinup-spdx.
      // let licenceTiles = 0;
      // const currentSpdxId = document
      //   .getElementById("licence-search")
      //   .value.toLowerCase();
      // licenceTile.forEach((element) => {
      //   if (currentSpdxId.length > 0) {
      //     const spdxId = element
      //       .getAttribute("data-joinup-spdx")
      //       .toLowerCase();
      //     if (
      //       spdxId.indexOf(currentSpdxId) === -1 &&
      //       !element.classList.contains("is-hidden")
      //     ) {
      //       element.classList.add("is-hidden");
      //     }
      //   }

      //   // Count visible tiles.
      //   if (!element.classList.contains("is-hidden")) {
      //     licenceTiles += 1;
      //   }
      // });

      // Show calculated number of tiles.
      // const licenceCounterNumber = document.querySelector(
      //   ".licence-counter__number"
      // );
      // licenceCounterNumber.textContent = licenceTiles;

      // Use plural only if licences count is higher than one.
      // const licenceCounterText = document.querySelector(
      //   ".licence-counter__text"
      // );
      // if (licenceTiles === 1) {
      //   licenceCounterText.textContent = "licence found";
      // } else {
      //   licenceCounterText.textContent = "licences found";
      // }
      // }

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

      // Cancel the 'Enter' key of the filter input. Entered key is cancelled on
      // keypress, not on keyup.
      // const searchInput = document.getElementById("licence-search");
      // searchInput.addEventListener("keypress", (e) => {
      //   if (e.key === "Enter") {
      //     e.preventDefault();
      //     return false;
      //   }
      // });
      // searchInput.addEventListener("keyup", () => {
      //   checkLicenceCategories();
      // });

      // Reset licence listing.
      const licenceResetButton = document.getElementById("licence-reset");

      licenceResetButton.addEventListener("click", () => {
        // const licenceSearchInput = document.querySelector(
        //   "input.licence-search__input"
        // );
        // const licenceFilterItem = document.querySelectorAll(
        //   ".licence-filter__item button.is-active"
        // );
        // licenceFilterItem.forEach((el) => {
        //   el.classList.remove("is-active");
        // });

        // licenceSearchInput.value = "";
        // checkLicenceCategories();
        // Also, uncheck any selected licences checked for comparison and disable
        // the "Compare" buttons. The 'data-joinup-licence-compare' attribute which is set
        // to [], is the attribute that stores the licences set-up for comparison.
        document
          .querySelectorAll(".licence-tile .form-check-input")
          .forEach((el) => {
            el.checked = false;
          });
        document
          .querySelector(".listing--licences")
          .setAttribute("data-joinup-licence-compare", "[]");
        // enableButtons(false);
      });

      function initCompatibleRadios() {
        const radios = document.querySelectorAll(
          ".jla--compatibility .form-check-input"
        );
        radios.forEach((element) => {
          // Uncheck radios on page load.
          element.checked = false;
          element.addEventListener("click", () => {
            const inboundLicence = document.querySelector(
              "input[name='inbound-licence']:checked"
            );
            const distributeLicence = document.querySelector(
              "input[name='outbound-licence']:checked"
            );
            if (inboundLicence && distributeLicence) {
              const compatibleUrl = `${
                drupalSettings.path.baseUrl
              }licence/compatibility-check/${inboundLicence.getAttribute(
                "data-joinup-licence-name"
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
