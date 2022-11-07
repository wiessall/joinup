/**
 * @file
 * JavaScript code for CKEditor Media label replace.
 */

((Drupal, once) => {
  Drupal.behaviors.joinupMediaCKEditor = {
    attach: function attach(context) {
      once("mediaLabelAlter", "div.editor-area", context).forEach((element) => {
        window.addEventListener("load", () => {
          const button = element.querySelector(
            'button[data-cke-tooltip-text="Insert Drupal Media"]'
          );
          button.setAttribute(
            "data-cke-tooltip-text",
            Drupal.t("Insert Media")
          );
        });
      });
    },
  };
})(Drupal, once);
