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
        const btnText = btn.querySelector(".fieldset-show-more__btn-text");
        const textMore = Drupal.t("Show more");
        const textLess = Drupal.t("Show less");
        function updateContent() {
          // Uses "aria-expanded" attribute to know the state of the collapse
          if (btn.getAttribute("aria-expanded") === "true") {
            btnText.textContent = textLess;
          } else {
            btnText.textContent = textMore;
          }
        }
        if (
          btn === null ||
          btn === undefined ||
          btnText === undefined ||
          btnText === undefined
        ) {
          return;
        }
        btn.addEventListener("click", updateContent);
      });
    },
  };
})(Drupal, once);
