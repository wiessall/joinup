/**
 * @file
 * Joinup solution behaviors.
 */
(function (Drupal) {
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

  function createNavigationLink(tab, text) {
    const link = document.createElement("a");
    link.href = tab.getAttribute("href");
    link.textContent = text;
    link.className = CONSTANTS.LINK_CLASSES.join(" ");
    link.addEventListener("click", (e) => {
      e.preventDefault();
      navigateToTab(tab);
    });
    return link;
  }

  function navigateToTab(tab) {
    tab.click();
    history.pushState(null, "", tab.getAttribute("href"));
    updatePrevNextLinks(tab);

    const targetElement = document.getElementById(
      CONSTANTS.SELECTORS.TARGET.slice(1),
    );
    if (targetElement) {
      targetElement.scrollIntoView({ behavior: "smooth" });
    }
  }

  function updatePrevNextLinks(selectedTab) {
    const actionsElement = getActionsElement();
    if (!actionsElement) return;

    // Remove old links
    actionsElement
      .querySelectorAll(CONSTANTS.SELECTORS.NAV_LINKS)
      .forEach((el) => el.remove());

    const allTabs = Array.from(
      document.querySelectorAll(`${CONSTANTS.SELECTORS.HORIZONTAL_TABS} li a`),
    );
    const currentIndex = allTabs.indexOf(selectedTab);

    const prevTab = allTabs[currentIndex > 0 ? currentIndex - 1 : 0];
    const nextTab = allTabs[Math.min(currentIndex + 1, allTabs.length - 1)];

    const prevLink = createNavigationLink(prevTab, "Previous");
    const nextLink = createNavigationLink(nextTab, "Next");

    actionsElement.prepend(nextLink, prevLink);
  }

  Drupal.behaviors.joinupSolutionTabsNavigation = {
    attach(context, settings) {
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
        updatePrevNextLinks(selectedTab);
      }

      // Event delegation for tab clicks
      horizontalTabsList.addEventListener("click", (e) => {
        const tabLink = e.target.closest("a");
        if (!tabLink) return;

        e.preventDefault();
        navigateToTab(tabLink);
      });
    },
  };
})(Drupal);
