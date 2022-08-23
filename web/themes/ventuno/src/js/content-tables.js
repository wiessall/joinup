/**
 * @file
 * DOM modification for content tables (raw user-inputted tables).
 */

Drupal.behaviors.contentTables = {
  attach() {
    const tables = document.querySelectorAll("#block-ventuno-content table");
    const wrapper = document.createElement("div");
    wrapper.classList.add("table-responsive", "mb-3");
    tables.forEach((element) => {
      element.classList.add("table");
      wrapper.appendChild(element.cloneNode(true));
      element.replaceWith(wrapper);
    });
  },
};
