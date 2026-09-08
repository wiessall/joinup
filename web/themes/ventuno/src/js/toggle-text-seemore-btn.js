/**
 * @param Drupal
 * @param once
 * @param drupalSettings
 * @file
 * Expands and collapses the text next to a "See more" button.
 *
 * The text is not hidden while collapsed, it is clamped to a few lines by the
 * 'see-more' component. Bootstrap's collapse widget cannot drive that, as it
 * hides its target outright, so the 'show' class the clamp keys off is toggled
 * here instead.
 *
 * A line clamp cannot be transitioned, and re-applying it would resize the text
 * in one step, so the clamp stays off for the whole animation and max-height is
 * moved between the clamped and the full height. Both are handed back to the
 * stylesheet once the transition ends.
 */

((Drupal, once, drupalSettings) => {
  // Matches the transition in see-more.scss, and is only a fallback for when no
  // transition runs at all, such as under prefers-reduced-motion.
  const DURATION = 350;

  Drupal.behaviors.toggleTextSeemoreBtn = {
    attach(context) {
      once("toggle-text-see-more", ".js-see-more-btn", context).forEach(
        (button) => {
          const controls = button.getAttribute("aria-controls");
          const target = document.querySelector(
            controls ? `#${controls}` : button.getAttribute("data-target"),
          );
          if (!target) {
            return;
          }

          // Measured while still clamped, so it survives the class toggle.
          const clampedHeight = target.clientHeight;
          let settle = null;
          let timer = null;

          const label = (expanded) => {
            const text = expanded ? Drupal.t("See less") : Drupal.t("See more");
            const icon = expanded ? "chevron-up" : "chevron-down";
            button.innerHTML = `${text} <svg class="bi icon--s"><use xlink:href="${drupalSettings.bcl_icon_path}#${icon}"></use></svg>`;
          };

          button.addEventListener("click", () => {
            const expanding = !target.classList.contains("show");

            // Drop the previous animation, if the button was clicked again
            // before it finished.
            if (settle) {
              target.removeEventListener("transitionend", settle);
              window.clearTimeout(timer);
            }

            // Unclamped for the whole animation, in both directions.
            target.style.maxHeight = `${target.clientHeight}px`;
            target.classList.add("show");

            const to = expanding ? target.scrollHeight : clampedHeight;

            settle = () => {
              if (!expanding) {
                target.classList.remove("show");
              }
              target.style.maxHeight = "";
              settle = null;
            };

            window.requestAnimationFrame(() => {
              target.style.maxHeight = `${to}px`;
              target.addEventListener("transitionend", settle, { once: true });
              timer = window.setTimeout(() => {
                target.removeEventListener("transitionend", settle);
                if (settle) {
                  settle();
                }
              }, DURATION + 50);
            });

            button.setAttribute("aria-expanded", expanding ? "true" : "false");
            label(expanding);
          });
        },
      );
    },
  };
})(Drupal, once, drupalSettings);
