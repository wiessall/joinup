/**
 * @file
 * Eif filter functionality.
 */

(() => {
  Drupal.behaviors.eifFilter = {
    attach() {
      function checkEifCategories() {
        const eifTile = document.querySelectorAll(".eif-tile");
        // Remove hidden class before further processing.
        eifTile.forEach((el) => {
          if (el.classList.contains("visually-hidden")) {
            el.classList.remove("visually-hidden");
          }
        });

        // Check every active filter item and hide tiles which don't contain proper
        // data-joinup-eif-category.
        let solutionsCount = 0;
        const eifFilterItem = document.querySelectorAll(
          ".eif-filter__item button.is-active",
        );
        eifFilterItem.forEach((element) => {
          const currentEifCategory = element.getAttribute(
            "data-joinup-eif-category",
          );

          if (typeof currentEifCategory !== "undefined") {
            eifTile.forEach((el) => {
              const eifCategory = el.getAttribute("data-joinup-eif-category");
              if (typeof eifCategory !== "undefined") {
                const eifCategoryArray = eifCategory.split(" ");
                if (
                  !eifCategoryArray.includes(currentEifCategory) &&
                  !el.classList.contains("visually-hidden")
                ) {
                  el.classList.add("visually-hidden");
                }
              }
            });
          }
        });
        // Show calculated number of solutions.
        eifTile.forEach((el) => {
          if (!el.classList.contains("visually-hidden")) {
            solutionsCount += 1;
          }
        });
        const counter = document.querySelector(".eif-counter__number");
        if (counter === null || counter === undefined) {
          return;
        }
        counter.textContent = solutionsCount;
      }
      // Trigger if eif filter is clicked.
      document.querySelectorAll(".eif-filter__item button").forEach((el) => {
        el.addEventListener("click", (e) => {
          e.preventDefault();
          e.target.classList.toggle("is-active");
          checkEifCategories();
        });
      });

      // Reset eif listing.
      const eifRest = document.getElementById("eif-reset");
      if (eifRest) {
        eifRest.addEventListener("click", () => {
          document
            .querySelector(".eif-filter__item button.is-active")
            .classList.remove("is-active");
          checkEifCategories();
        });
      }

      // Filter on window load. Needed for eif search filter.
      window.onload = () => {
        checkEifCategories();
      };
    },
  };
})();
