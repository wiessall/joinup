/**
 * @file
 * Joinup solution behaviors.
 */
((Drupal, window, $) => {
  // Extend Drupal.HorizontalTab to dispatch an event on tab focus.
  const originalFocus = Drupal.HorizontalTab.prototype.focus;
  Drupal.HorizontalTab.prototype.focus = function patchedFocus(...args) {
    originalFocus.apply(this, args);
    $(this.details).trigger("horizontalTabFocused", [this]);
  };

  Drupal.behaviors.joinupSolutionTabsNavigation = {
    attach(context) {
      once(
        "joinup-solution-tabs-navigation",
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

        // Ensure the active tab input is set to the first pane if not already set.
        const getActiveIndex = () => {
          const activeId = activeTabInput.value;
          return panes.findIndex((pane) => pane.id === activeId);
        };

        // Create a button with the specified label and activation function.
        const createButton = (label, onActivate) => {
          const btn = document.createElement("button");
          btn.type = "button";
          btn.className = "btn btn-secondary me-1";
          btn.textContent = label;
          btn.addEventListener("click", onActivate);
          btn.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") {
              e.preventDefault();
              onActivate();
            }
          });
          return btn;
        };

        // Focus the tab at the specified offset from the current active tab.
        function focusTabAtOffset(offset) {
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
        }

        // Create buttons before using them.
        const prevButton = createButton(Drupal.t("Previous"), () =>
          focusTabAtOffset(-1),
        );
        const nextButton = createButton(Drupal.t("Next"), () =>
          focusTabAtOffset(1),
        );

        // Update the state of the previous and next buttons based on the active tab index.
        const updateButtonState = () => {
          const index = getActiveIndex();
          prevButton.disabled = index <= 0;
          nextButton.disabled = index >= panes.length - 1;
        };

        // Prepend buttons to the target location.
        targetLocation.prepend(nextButton);
        targetLocation.prepend(prevButton);

        // Update buttons state on init and tab focus event.
        updateButtonState();
        panes.forEach((pane) => {
          $(pane).on("horizontalTabFocused", () => {
            updateButtonState();
          });
        });
      });
    },
  };
})(Drupal, window, jQuery);
