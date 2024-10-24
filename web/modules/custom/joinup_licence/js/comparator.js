/**
 * @file
 * Licence comparison functionality.
 */

((drupalSettings) => {
  Drupal.behaviors.licenceComparison = {
    attach() {
      // Disable or enable all compare checkboxes.
      function disableCheckboxes(state) {
        document
          .querySelectorAll(".licence-tile .form-check-input[type='checkbox']")
          .forEach((element) => {
            if (!element.checked) {
              element.disabled = state;
            }
          });
      }

      // Enable or disable all compare buttons.
      function enableButtons(state) {
        const buttons = document.querySelectorAll(".jla-compare");
        if (state) {
          buttons.forEach((element) => {
            if (element.hasAttribute("disabled")) {
              element.removeAttribute("disabled");
            }
            if (element.hasAttribute("aria-disabled")) {
              element.removeAttribute("aria-disabled");
            }
          });
        } else {
          buttons.forEach((element) => {
            element.setAttribute("disabled", "");
            element.setAttribute("aria-disabled", "true");
          });
        }
      }

      // Check status of compare elements and push the array of licence names
      // (data-joinup-licence-name) to compare as data-joinup-licence-compare
      // attribute to the listing wrapper.
      function checkCompareStatus() {
        let licencesString = "";
        const maxCompare = 5;
        const licencesArray = [];
        let licenceName = "";
        document
          .querySelectorAll(".licence-tile .form-check-input[type='checkbox']")
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

        const licenceListing = document.querySelector(".listing--licences");
        if (licenceListing) {
          licenceListing.setAttribute(
            "data-joinup-licence-compare",
            licencesString,
          );
        }
      }

      // Reset licence listing.
      const licenceResetButton = document.getElementById("licence-reset");

      licenceResetButton.addEventListener("click", () => {
        // Also, uncheck any selected licences checked for comparison and disable
        // the "Compare" buttons. The 'data-joinup-licence-compare' attribute which is set
        // to [], is the attribute that stores the licences set-up for comparison.
        document
          .querySelectorAll("input[type='checkbox'][data-joinup-licence-name]")
          .forEach((el) => {
            el.checked = false;
            el.disabled = false;
          });
        document
          .querySelector(".listing--licences")
          .setAttribute("data-joinup-licence-compare", "[]");
        enableButtons(false);
      });

      // Change compare elements if the checkbox is clicked.
      document
        .querySelectorAll(".licence-tile .form-check-input[type='checkbox']")
        .forEach((element) => {
          element.addEventListener("click", (e) => {
            const licenceListing = document.querySelector(".listing--licences");
            const licences = licenceListing.getAttribute(
              "data-joinup-licence-compare",
            );
            const licenceName = e.target.getAttribute(
              "data-joinup-licence-name",
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
              licencesString,
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
      checkCompareStatus();
    },
  };
})(drupalSettings);
