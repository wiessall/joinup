(function (factory) {
  typeof define === 'function' && define.amd ? define(factory) :
  factory();
})((function () { 'use strict';

  /**
   * @file
   * DOM modification for content tables (raw user-inputted tables).
   */

  (() => {
    Drupal.behaviors.contentTables = {
      attach() {
        const tables = document.querySelectorAll("#block-ventuno-content table");
        tables.forEach(element => {
          const wrapper = document.createElement("div");
          wrapper.classList.add("table-responsive", "mb-3");
          element.classList.add("table");
          wrapper.appendChild(element.cloneNode(true));
          element.replaceWith(wrapper);
        });
      }
    };
  })();

}));
//# sourceMappingURL=content-tables.js.map
