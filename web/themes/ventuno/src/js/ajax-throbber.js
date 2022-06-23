/**
 * @file
 * Theme override for Drupal.theme.ajaxProgressThrobber.
 *
 * @see Drupal.theme.ajaxProgressThrobber
 */
(() => {
  Drupal.theme.ajaxProgressThrobber = () => {
    const spinner = document.createElement("div");
    spinner.classList.add("spinner-border");
    spinner.classList.add("position-absolute");
    spinner.classList.add("m-1");
    spinner.classList.add("text-primary");
    spinner.innerHTML = Drupal.t(
      "<span class='visually-hidden'>Loading&hellip;</div>",
      {},
      {
        context: "Loading text for Drupal cores Ajax throbber (inline)",
      }
    );
    return spinner;
  };
})();
