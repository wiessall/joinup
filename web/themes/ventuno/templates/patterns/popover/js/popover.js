/**
 * @file
 * Generic Ventuno Popover functionality.
 */

((Drupal, once) => {
  const FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

  Drupal.behaviors.ventunoPopover = {
    attach(context) {
      const triggers = once(
        "ventuno-popover",
        ".ventuno-popover__trigger",
        context,
      );

      triggers.forEach((trigger) => {
        const popover = trigger.closest(".ventuno-popover");
        const targetId = trigger.getAttribute("data-popover-target");
        const content = document.getElementById(targetId);

        if (!content) return;

        // Initialize hidden state
        content.setAttribute("aria-hidden", "true");
        content.querySelectorAll(FOCUSABLE).forEach((el) => {
          el.setAttribute("tabindex", "-1");
        });

        // Initialize Bootstrap popover
        const bsPopover = new bootstrap.Popover(trigger, {
          content: content.innerHTML,
          html: true,
          placement: trigger.getAttribute("data-bs-placement") || "bottom",
          trigger: "click",
          customClass: "ventuno-popover-component",
          boundary: "viewport",
          fallbackPlacements: ["top", "right"],
        });

        // Focus trap handler
        const trapFocus = (event, tip) => {
          if (event.key !== "Tab") return;

          const focusableEls = tip.querySelectorAll(FOCUSABLE);
          if (!focusableEls.length) return;

          const firstEl = focusableEls[0];
          const lastEl = focusableEls[focusableEls.length - 1];

          if (event.shiftKey && document.activeElement === firstEl) {
            event.preventDefault();
            lastEl.focus();
          } else if (!event.shiftKey && document.activeElement === lastEl) {
            event.preventDefault();
            firstEl.focus();
          }
        };

        // When popover is shown
        trigger.addEventListener("shown.bs.popover", () => {
          content.setAttribute("aria-hidden", "false");

          const { tip } = bsPopover;
          if (tip) {
            // Accessible container
            tip.setAttribute("role", "dialog");
            tip.setAttribute("tabindex", "-1");
            const label =
              trigger.getAttribute("aria-label") ||
              trigger.textContent?.trim() ||
              "Popover";
            tip.setAttribute("aria-label", label);

            // Move focus inside
            requestAnimationFrame(() => {
              const firstFocusable = tip.querySelector(FOCUSABLE) || tip;
              firstFocusable.focus({ preventScroll: true });
            });

            // Trap focus
            tip.addEventListener("keydown", (e) => trapFocus(e, tip));

            // Close button
            const closeButton = tip.querySelector(".ventuno-popover__close");
            if (closeButton) {
              closeButton.addEventListener(
                "click",
                (event) => {
                  event.stopPropagation();
                  bsPopover.hide();
                },
                { once: true },
              );
            }

            // ESC closes popover
            tip.addEventListener(
              "keydown",
              (event) => {
                if (event.key === "Escape") {
                  bsPopover.hide();
                }
              },
              { once: true },
            );
          }
        });

        // When popover is hidden
        trigger.addEventListener("hidden.bs.popover", () => {
          content.setAttribute("aria-hidden", "true");
          content.querySelectorAll(FOCUSABLE).forEach((el) => {
            el.setAttribute("tabindex", "-1");
          });

          // Return focus to trigger
          trigger.focus({ preventScroll: true });
        });

        // Close on outside click
        document.addEventListener("click", (event) => {
          if (
            !popover.contains(event.target) &&
            !event.target.closest(".popover.ventuno-popover-component")
          ) {
            bsPopover.hide();
          }
        });

        // Close on Escape from trigger
        trigger.addEventListener("keydown", (event) => {
          if (event.key === "Escape") {
            bsPopover.hide();
          }
        });
      });
    },
  };
})(Drupal, once);
