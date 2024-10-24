/**
 * @file
 * Licence filter functionality.
 *
 * Gathers all licence tiles, removes all hidden classes, checks active filter
 * items and hides tiles accordingly, updates visible tiles counter.
 */

(() => {
  Drupal.behaviors.licenceFilter = {
    attach() {
      function checkLicenceCategories() {
        const licenceTile = document.querySelectorAll(".licence-tile");

        // Remove hidden styles (if any) before further processing.
        licenceTile.forEach((el) => {
          if (el.style.display === "none") {
            el.style.removeProperty("display");
          }
          if (el.getAttribute("aria-hidden") === "true") {
            el.removeAttribute("aria-hidden");
          }
        });

        // Check every active filter item and hide tiles which don't contain proper
        // data-joinup-licence-category.
        const licenceFilterItem = document.querySelectorAll(
          ".licence-filter__item button.is-active",
        );
        licenceFilterItem.forEach((element) => {
          const currentlicenceCategory = element.getAttribute(
            "data-joinup-licence-category",
          );
          if (typeof currentlicenceCategory !== "undefined") {
            licenceTile.forEach((el) => {
              const licenceCategory = el.getAttribute(
                "data-joinup-licence-category",
              );
              if (typeof licenceCategory !== "undefined") {
                const licenceCategoryArray = licenceCategory.split(" ");
                if (
                  !licenceCategoryArray.includes(currentlicenceCategory) &&
                  el.style.display !== "none"
                ) {
                  el.style.setProperty("display", "none");
                  el.setAttribute("aria-hidden", "true");
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
              element.style.display !== "none"
            ) {
              element.style.setProperty("display", "none");
              element.setAttribute("aria-hidden", "true");
            }
          }

          // Count visible tiles.
          if (element.style.display !== "none") {
            licenceTiles += 1;
          }
        });

        // Show calculated number of tiles.
        const licenceCounterNumber = document.querySelector(
          ".licence-counter__number",
        );
        if (licenceCounterNumber) {
          licenceCounterNumber.textContent = licenceTiles;
        }

        // Use plural only if licences count is higher than one.
        const licenceCounterText = document.querySelector(
          ".licence-counter__text",
        );
        if (licenceCounterText) {
          licenceCounterText.textContent =
            licenceTiles === 1 ? "licence found" : "licences found";
        }
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
          "input.licence-search__input",
        );
        const licenceFilterItem = document.querySelectorAll(
          ".licence-filter__item button.is-active",
        );
        licenceFilterItem.forEach((el) => {
          el.classList.remove("is-active");
        });

        licenceSearchInput.value = "";
        checkLicenceCategories();
      });
      checkLicenceCategories();
    },
  };
})();
