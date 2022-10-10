/**
 * @file
 * Licence filter functionality.
 */

((drupalSettings) => {
  Drupal.behaviors.licenceFilter = {
    attach() {
      /**
       * Gathers all licence tiles,
       * removes all hidden classes,
       * checks active filter items and hides tiles accordingly,
       * updates visible tiles counter.
       */
      function checkLicenceCategories() {
        const licenceTile = document.querySelectorAll(".licence-tile");

        // Remove hidden class before further processing.
        licenceTile.forEach((el) => {
          if (el.classList.contains("is-hidden")) {
            el.classList.remove("is-hidden");
          }
        });

        // Check every active filter item and hide tiles which don't contain proper
        // data-joinup-licence-category.
        const licenceFilterItem = document.querySelectorAll(
          ".licence-filter__item button.is-active"
        );
        licenceFilterItem.forEach((element) => {
          const currentlicenceCategory = element.getAttribute(
            "data-joinup-licence-category"
          );
          if (typeof currentlicenceCategory !== "undefined") {
            licenceTile.forEach((el) => {
              const licenceCategory = el.getAttribute(
                "data-joinup-licence-category"
              );
              if (typeof licenceCategory !== "undefined") {
                const licenceCategoryArray = licenceCategory.split(" ");
                if (
                  !licenceCategoryArray.includes(currentlicenceCategory) &&
                  !el.classList.contains("is-hidden")
                ) {
                  el.classList.add("is-hidden");
                }
              }
            });
          }
        });

        // Check licence search field value and hide tiles which don't contain
        // proper data-spdx.
        let licenceTiles = 0;
        const currentSpdxId = document
          .getElementById("licence-search")
          .value.toLowerCase();
        licenceTile.forEach((element) => {
          if (currentSpdxId.length > 0) {
            const spdxId = element
              .getAttribute("data-joinup-spdx")
              .toLowerCase();
            if (
              spdxId.indexOf(currentSpdxId) === -1 &&
              !element.classList.contains("is-hidden")
            ) {
              element.classList.add("is-hidden");
            }
          }

          // Count visible tiles.
          if (!element.classList.contains("is-hidden")) {
            licenceTiles += 1;
          }
        });

        // Show calculated number of tiles.
        const licenceCounterNumber = document.querySelector(
          ".licence-counter__number"
        );
        licenceCounterNumber.textContent = licenceTiles;

        // Use plural only if licences count is higher than one.
        const licenceCounterText = document.querySelector(
          ".licence-counter__text"
        );
        if (licenceTiles === 1) {
          licenceCounterText.textContent = "licence found";
        } else {
          licenceCounterText.textContent = "licences found";
        }
      }

      // Disable or enable all compare checkboxes.
      function disableCheckboxes(state) {
        document
          .querySelectorAll(".licence-tile .form-check-input")
          .forEach((element) => {
            if (!element.checked) {
              element.disabled = state;
            }
          });
      }

      // Enable or disable all compare buttons.
      function enableButtons(state) {
        const btnSelector = document.querySelectorAll(
          ".licence-tile__button--compatible"
        ).length
          ? ".licence-tile__button--compatible"
          : ".licence-tile__button--compare";
        if (state) {
          const buttons = document.querySelectorAll(`${btnSelector}`);
          buttons.forEach((element) => {
            if (element.hasAttribute("disabled")) {
              element.removeAttribute("disabled");
              element.removeAttribute("aria-disabled");
            }
          });
        } else {
          document.querySelectorAll(`${btnSelector}`).forEach((element) => {
            element.setAttribute("disabled", "");
            element.setAttribute("aria-disabled", "true");
          });
        }
      }

      // Check status of compare elements and refresh.
      function checkCompareStatus() {
        let licencesString = "";
        const maxCompare = 5;
        const licencesArray = [];
        const licenceListing = document.querySelector(".listing--licences");
        let licenceName = "";
        document
          .querySelectorAll(".licence-tile .form-check-input")
          .forEach((element) => {
            if (element.checked) {
              licenceName = element.getAttribute("data-joinup-licence-name");
              licencesArray.push(licenceName);
              licencesString = JSON.stringify(licencesArray);
              if (licencesArray.length >= maxCompare) {
                disableCheckboxes(true);
              }
              if (licencesArray.length >= 2) {
                enableButtons(true);
              }
            }
          });
        licenceListing.setAttribute(
          "data-joinup-licence-compare",
          licencesString
        );
      }

      // Trigger if licence filter is clicked.
      document
        .querySelectorAll(".licence-filter__item button")
        .forEach((element) => {
          element.addEventListener("click", (e) => {
            e.preventDefault();
            e.target.classList.toggle("is-active");
            checkLicenceCategories();
          });
        });

      // Cancel the 'Enter' key of the filter input. Entered key is cancelled on
      // keypress, not on keyup.
      const searchInput = document.getElementById("licence-search");
      searchInput.addEventListener("keypress", (e) => {
        if (e.key === "Enter") {
          e.preventDefault();
          return false;
        }
      });
      searchInput.addEventListener("keyup", () => {
        checkLicenceCategories();
      });

      // Reset licence listing.
      const licenceResetButton = document.getElementById("licence-reset");

      licenceResetButton.addEventListener("click", () => {
        const licenceSearchInput = document.querySelector(
          "input.licence-search__input"
        );
        const licenceFilterItem = document.querySelectorAll(
          ".licence-filter__item button.is-active"
        );
        licenceFilterItem.forEach((el) => {
          el.classList.remove("is-active");
        });

        licenceSearchInput.value = "";
        // document
        //   .querySelector(".licence-search__input .mdl-js-textfield")
        //   .MaterialTextfield.checkDirty();
        checkLicenceCategories();
        // Also, uncheck any selected licences checked for comparison and disable
        // the "Compare" buttons. The 'data-joinup-licence-compare' attribute which is set
        // to [], is the attribute that stores the licences set-up for comparison.
        if (
          document.querySelectorAll(".licence-tile__button--compatible").length
        ) {
          document
            .querySelectorAll(".licence-tile .form-check-input[type='radio']")
            .forEach((el) => {
              el.MaterialRadio.uncheck();
            });
        } else {
          document
            .querySelectorAll(
              ".licence-tile .form-check-input[type='checkbox']"
            )
            .forEach((el) => {
              el.disabled = true;
            });
        }
        document
          .querySelector(".listing--licences")
          .setAttribute("data-joinup-licence-compare", "[]");
        enableButtons(false);
      });

      // Change compare elements if the checkbox is clicked.
      document
        .querySelectorAll(".licence-tile .form-check-input")
        .forEach((element) => {
          element.addEventListener("click", (e) => {
            const licenceListing = document.querySelector(".listing--licences");
            const licences = licenceListing.getAttribute(
              "data-joinup-licence-compare"
            );
            const licenceName = e.target.getAttribute(
              "data-joinup-licence-name"
            );
            let licencesString = "";
            let licencesArray = [];
            const maxCompare = drupalSettings.licenceComparer.maxLicenceCount;
            if (licences.length > 0) {
              licencesArray = JSON.parse(licences);
            }
            if (e.target.checked) {
              licencesArray.push(licenceName);
              licencesString = JSON.stringify(licencesArray);
              if (licencesArray.length >= maxCompare) {
                disableCheckboxes(true);
              }
              if (licencesArray.length >= 2) {
                enableButtons(true);
              }
            } else {
              if (licencesArray.length > 0) {
                licencesArray = JSON.parse(licences);
                // eslint-disable-next-line max-nested-callbacks
                licencesArray = licencesArray.filter((value) => {
                  return value !== licenceName;
                });
                licencesString = JSON.stringify(licencesArray);
              }
              if (licencesArray.length === maxCompare - 1) {
                disableCheckboxes(false);
              }
              if (licencesArray.length < 2) {
                enableButtons(false);
              }
            }
            licenceListing.setAttribute(
              "data-joinup-licence-compare",
              licencesString
            );
          });
        });
      // Click in compare button.
      document
        .querySelectorAll(".licence-tile__button--compare")
        .forEach((element) => {
          element.addEventListener("click", (e) => {
            e.preventDefault();
            const licences = document
              .querySelector(".listing--licences")
              .getAttribute("data-joinup-licence-compare");
            if (licences.length > 0) {
              const licencesArray = JSON.parse(licences);
              window.location.href = `${
                drupalSettings.licenceComparer.path
              }/${licencesArray.join(";")}`;
            }
          });
        });

      function initCompatibleRadios() {
        const radios = document.querySelectorAll(
          ".jla--compatibility .form-check-input[type='radio']"
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
              const licenceTileCompatibleButton = document.querySelectorAll(
                ".licence-tile__button--compatible"
              );
              // eslint-disable-next-line max-nested-callbacks
              licenceTileCompatibleButton.forEach((el) => {
                el.setAttribute("href", compatibleUrl);
              });
              enableButtons(true);
            }
          });
        });
      }

      // Filter on window load. Needed for licence search filter.
      window.onload = () => {
        checkLicenceCategories();
        checkCompareStatus();
        initCompatibleRadios();
      };
    },
  };
})(drupalSettings);
