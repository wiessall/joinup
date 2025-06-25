/**
 * @file
 * Joinup solution behaviors.
 */
((Drupal, window) => {
  const CONSTANTS = {
    LINK_CLASSES: ["btn", "btn-primary", "joinup-tabs-nav-link"],
    SELECTORS: {
      ACTIONS: "#edit-actions",
      HORIZONTAL_TABS: ".horizontal-tabs-list",
      NAV_LINKS: ".joinup-tabs-nav-link",
      TARGET: "#edit-group-main",
    },
  };

  let cachedActionsElement;

  function getActionsElement() {
    if (!cachedActionsElement) {
      cachedActionsElement = document.querySelector(
        CONSTANTS.SELECTORS.ACTIONS,
      );
    }
    return cachedActionsElement;
  }

  // Combined navigation functionality into a single function
  function handleTabInteraction(currentTab, isInit = false) {
    if (!isInit) {
      currentTab.click();
      window.location.hash = currentTab.getAttribute("href");
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
      { targetTab: prevTab, text: "Previous" },
      { targetTab: nextTab, text: "Next" },
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
    },
  };
})(Drupal, window);
