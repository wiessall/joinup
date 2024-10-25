/**
 * @file
 * Updates text content for "Show more" buttons depending on open/close state.
 */

((Drupal, once) => {
  Drupal.behaviors.fieldsetShowMore = {
    attach(context) {
      // Uses "once" to avoid adding duplicated event listeners
      const fieldsets = once(
        "fieldset-show-more",
        document.querySelectorAll(".fieldset-show-more"),
        context,
      );
      // Ensures "Show more" functionality in case there are multiple fieldsets
      fieldsets.forEach((fieldset) => {
        const btn = fieldset.querySelector(".fieldset-show-more__btn");
        const btnText = btn
          ? btn.querySelector(".fieldset-show-more__btn-text")
          : null;

        if (btn && btnText) {
          btn.addEventListener("click", () => {
            // Uses "aria-expanded" attribute to know the state of the collapse
            btnText.textContent =
              btn.getAttribute("aria-expanded") === "true"
                ? Drupal.t("Show less")
                : Drupal.t("Show more");
          });
        }
      });
    },
  };
})(Drupal, once);
