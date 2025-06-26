/**
 * @file
 * Joinup solution behaviors.
 */
((Drupal, window) => {
  // Define constants for the selectors and classes used in the script.
  const CONSTANTS = {
    LINK_CLASSES: ["btn", "btn-secondary", "joinup-tabs-nav-link", "me-1"],
    SELECTORS: {
      ACTIONS: "#edit-actions",
      HORIZONTAL_TABS: ".horizontal-tabs-list",
      NAV_LINKS: ".joinup-tabs-nav-link",
      TARGET: "#edit-group-main",
    },
  };

  // Cache the actions element to avoid repeated DOM queries.
  let cachedActionsElement;

  /**
   * Get the actions element.
   */
  function getActionsElement() {
    if (!cachedActionsElement) {
      cachedActionsElement = document.querySelector(
        CONSTANTS.SELECTORS.ACTIONS,
      );
    }
    return cachedActionsElement;
  }

  /**
   * Recreate the navigation links based on on the click event of the tabs.
   *
   * @param {HTMLElement} currentTab
   *   The currently selected tab element.
   * @param {boolean} [isInit=false]
   *   A boolean indicating if this is the initial call (default is false).
   */
  function handleTabInteraction(currentTab, isInit = false) {
    // The if statement mainly avoids adding the fragment of the default tab
    // to window.location.hash on page load.
    if (!isInit) {
      currentTab.click();
      window.history.replaceState(null, "", currentTab.getAttribute("href"));
    }

    const targetElement = document.getElementById(
      CONSTANTS.SELECTORS.TARGET.slice(1),
    );
    if (targetElement) {
      targetElement.scrollIntoView({ behavior: "smooth" });
    }

    const actionsElement = getActionsElement();
    if (!actionsElement) return;

    // Remove old links
    actionsElement
      .querySelectorAll(CONSTANTS.SELECTORS.NAV_LINKS)
      .forEach((el) => el.remove());

    const allTabs = Array.from(
      document.querySelectorAll(`${CONSTANTS.SELECTORS.HORIZONTAL_TABS} li a`),
    );
    const currentIndex = allTabs.indexOf(currentTab);

    const prevTab = allTabs[currentIndex > 0 ? currentIndex - 1 : 0];
    const nextTab = allTabs[Math.min(currentIndex + 1, allTabs.length - 1)];

    // Create navigation links
    [
      { targetTab: nextTab, text: "Next" },
      { targetTab: prevTab, text: "Previous" },
    ].forEach(({ targetTab, text }) => {
      const link = document.createElement("a");
      link.href = targetTab.getAttribute("href");
      link.textContent = text;
      link.className = CONSTANTS.LINK_CLASSES.join(" ");
      link.addEventListener("click", (e) => {
        e.preventDefault();
        handleTabInteraction(targetTab);
      });
      actionsElement.prepend(link);
    });
  }

  /**
   * Drupal behavior to handle solution tabs navigation.
   *
   * This behavior initializes the tab navigation by prepending a "Previous"
   * and "Next" link in the list of actions. Checks whether there are available
   * tabs - thus does not interfere with the confirmation screen.
   */
  Drupal.behaviors.joinupSolutionTabsNavigation = {
    attach(context) {
      const actionsElement = context.querySelector(CONSTANTS.SELECTORS.ACTIONS);
      const horizontalTabsList = context.querySelector(
        CONSTANTS.SELECTORS.HORIZONTAL_TABS,
      );

      if (!actionsElement || !horizontalTabsList) return;

      const listItems = horizontalTabsList.querySelectorAll("li");
      if (listItems.length === 0) return;

      // Initialize selected tab
      const fragment = window.location.hash;
      const selectedTab = fragment
        ? horizontalTabsList.querySelector(`li a[href="${fragment}"]`)
        : horizontalTabsList.querySelector("li a");

      if (selectedTab) {
        handleTabInteraction(selectedTab, true);
      }

      // Event delegation for tab clicks
      horizontalTabsList.addEventListener("click", (e) => {
        const tabLink = e.target.closest("a");
        if (!tabLink) return;

        e.preventDefault();
        handleTabInteraction(tabLink);
      });

      // Replace .btn-outline-danger with .btn-primary in the actions section.
      const dangerButton = actionsElement.querySelector(".btn-outline-danger");
      if (dangerButton) {
        dangerButton.classList.remove("btn-outline-danger");
        dangerButton.classList.add("btn-primary");
      }
    },
  };
})(Drupal, window);
