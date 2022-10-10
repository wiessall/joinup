/**
 * @file
 * Licence filter functionality.
 */

((drupalSettings) => {
  Drupal.behaviors.licenceFilter = {
    attach() {
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
        // proper data-joinup-spdx.
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
        if (state) {
          const buttons = document.querySelectorAll(".jla-compare");
          buttons.forEach((element) => {
            if (element.hasAttribute("disabled")) {
              element.removeAttribute("disabled");
              element.removeAttribute("aria-disabled");
            }
          });
        } else {
          document.querySelectorAll(".jla-compare").forEach((element) => {
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
        checkLicenceCategories();
        // Also, uncheck any selected licences checked for comparison and disable
        // the "Compare" buttons. The 'data-joinup-licence-compare' attribute which is set
        // to [], is the attribute that stores the licences set-up for comparison.
        document
          .querySelectorAll(".licence-tile .form-check-input")
          .forEach((el) => {
            el.disabled = true;
          });
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
      document.querySelectorAll(".jla-compare").forEach((element) => {
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

      // Filter on window load. Needed for licence search filter.
      window.onload = () => {
        checkLicenceCategories();
        checkCompareStatus();
      };
    },
  };
})(drupalSettings);
