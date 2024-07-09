/**
 * @file
 * Theme override for Drupal.theme.ajaxProgressThrobber.
 *
 * @see Drupal.theme.ajaxProgressThrobber
 */
(() => {
  Drupal.theme.ajaxProgressThrobber = () => {
    const loading = Drupal.t(
      "Loading",
      {},
      { context: "Loading text for Drupal cores Ajax throbber (inline)" },
    );
    const spinner = document.createElement("div");
    spinner.classList.add("spinner-border");
    spinner.classList.add("position-absolute");
    spinner.classList.add("m-1");
    spinner.classList.add("text-primary");
    spinner.innerHTML = `<span class='visually-hidden'>${loading}&hellip;</div>`;
    return spinner;
  };
})();
