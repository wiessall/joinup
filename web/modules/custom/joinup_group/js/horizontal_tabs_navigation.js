/**
 * @file
 * Horizontal tabs navigation behavior.
 *
 * Adds previous/next buttons to control horizontal tabs.
 */
((Drupal, window, $) => {
  // Extend Drupal.HorizontalTab to dispatch an event on tab focus.
  const originalFocus = Drupal.HorizontalTab.prototype.focus;
  Drupal.HorizontalTab.prototype.focus = function patchedFocus(...args) {
    originalFocus.apply(this, args);
    $(this.details).trigger("horizontalTabFocused", [this]);
  };

  Drupal.behaviors.horizontalTabsNavigation = {
    attach(context) {
      once(
        "horizontal-tabs-navigation",
        "[data-horizontal-tabs]",
        context,
      ).forEach((tabsWrapper) => {
        const tabPanesWrapper = tabsWrapper.querySelector(
          "[data-horizontal-tabs-panes]",
        );
        const activeTabInput = tabPanesWrapper.querySelector(
          '[type="hidden"].horizontal-tabs-active-tab',
        );
        const panes = Array.from(
          tabPanesWrapper.querySelectorAll("details.horizontal-tabs-pane"),
        );
        const targetLocation = context.querySelector("#edit-actions");

        if (!activeTabInput || !targetLocation || panes.length === 0) {
          return; // Required DOM elements not found, exit early.
        }

        // Determine the index of the currently active tab.
        const getActiveIndex = () => {
          const activeId = activeTabInput.value;
          return panes.findIndex((pane) => pane.id === activeId);
        };

        // Focus the tab at the specified offset from the current active tab.
        const focusTabAtOffset = (offset) => {
          const activeIndex = getActiveIndex();
          const targetPane = panes[activeIndex + offset];
          const horizontalTab = $(targetPane).data("horizontalTab");
          if (horizontalTab) {
            horizontalTab.focus();
            horizontalTab.link?.[0]?.scrollIntoView({
              behavior: "smooth",
              block: "start",
            });
          }
        };

        // Helper to create navigation buttons.
        const createButton = (label, offset) => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-secondary me-1";
          btn.textContent = Drupal.t(label);
          btn.setAttribute("aria-label", `${label} tab`);
          btn.addEventListener("click", () => focusTabAtOffset(offset));
          btn.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
              e.preventDefault();
              focusTabAtOffset(offset);
            }
          });
          return btn;
        };

        // Create navigation buttons.
        const prevButton = createButton("Previous", -1);
        const nextButton = createButton("Next", 1);

        // Update the state of the previous and next buttons.
        const updateButtonState = () => {
          const index = getActiveIndex();
          prevButton.disabled = index <= 0;
          nextButton.disabled = index >= panes.length - 1;

          if (index > 0) {
            prevButton.setAttribute("aria-controls", panes[index - 1].id);
          } else {
            prevButton.removeAttribute("aria-controls");
          }

          if (index < panes.length - 1) {
            nextButton.setAttribute("aria-controls", panes[index + 1].id);
          } else {
            nextButton.removeAttribute("aria-controls");
          }
        };

        // Prepend buttons to the target location.
        targetLocation.prepend(nextButton);
        targetLocation.prepend(prevButton);

        // Update buttons state on init and tab focus event.
        updateButtonState();
        panes.forEach((pane) => {
          $(pane).on("horizontalTabFocused", updateButtonState);
        });
      });
    },
  };
})(Drupal, window, jQuery);
