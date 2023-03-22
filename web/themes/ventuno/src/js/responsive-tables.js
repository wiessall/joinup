/**
 * @file
 * Adds a wrapper around the table to prevent it overflowing to the right on
 * small screens.
 */

(() => {
  Drupal.behaviors.responsiveTables = {
    attach() {
      const tables = document.querySelectorAll("#block-ventuno-content table");
      tables.forEach((element) => {
        const wrapper = document.createElement("div");
        wrapper.classList.add("table-responsive", "mb-3");
        element.classList.add("table");
        wrapper.appendChild(element.cloneNode(true));
        element.replaceWith(wrapper);
      });
    },
  };
})();
