/**
 * @file
 * Exposes an 'outdated content' notice if case.
 */

((Drupal, once) => {
  Drupal.eTransDebugger = Drupal.eTransDebugger || {};
  Drupal.eTransDebugger.includedSelector =
    Drupal.eTransDebugger.includedSelector || "";
  Drupal.eTransDebugger.excludedSelector =
    Drupal.eTransDebugger.excludeSelector || "";
  /**
   * Makes a notice available in the window object if the page displays an
   * entity whose content is outdated so that the theme layer can render it.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches outdated content behaviors.
   */
  Drupal.behaviors.eTransDebugger = {
    attach(context) {
      once(
        "etrans-debugger",
        "body script[type='application/json']",
        context
      ).forEach((element) => {
        const json = JSON.parse(element.textContent);
        if (!json.service || json.service !== "etrans") {
          return;
        }
        const { include, exclude } = json;
        Drupal.eTransDebugger.includedSelector += Drupal.eTransDebugger
          .includedSelector
          ? `,${include}`
          : include;
        Drupal.eTransDebugger.excludedSelector += Drupal.eTransDebugger
          .excludedSelector
          ? `,${exclude}`
          : exclude;
      });

      if (Drupal.eTransDebugger.includedSelector) {
        document
          .querySelectorAll(Drupal.eTransDebugger.includedSelector)
          .forEach((element) => {
            element.classList.remove("etrans-debugger-excluded");
            element.classList.add("etrans-debugger-included");
          });
      }

      if (Drupal.eTransDebugger.excludedSelector) {
        document
          .querySelectorAll(Drupal.eTransDebugger.excludedSelector)
          .forEach((element) => {
            element.classList.remove("etrans-debugger-included");
            element.classList.add("etrans-debugger-excluded");
          });
      }
    },
  };
})(Drupal, once);
