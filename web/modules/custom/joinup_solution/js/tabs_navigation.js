/**
 * @file
 * Joinup solution behaviors.
 */
(function (Drupal) {

  'use strict';

  function updatePrevNextLinks(selectedTab) {
    const actionsElement = document.querySelector('#edit-actions');
    if (!actionsElement) return;

    // Remove old links if present
    actionsElement.querySelectorAll('.joinup-tabs-nav-link').forEach(el => el.remove());

    const allTabs = Array.from(document.querySelectorAll('.horizontal-tabs-list li a'));
    const currentIndex = allTabs.indexOf(selectedTab);

    // Determine previous and next tabs
    const prevTab = allTabs[currentIndex > 0 ? currentIndex - 1 : 0];
    const nextTab = allTabs[currentIndex < allTabs.length - 1 ? currentIndex + 1 : allTabs.length - 1];

    // Create Previous link
    const prevLink = document.createElement('a');
    prevLink.href = prevTab.getAttribute('href');
    prevLink.textContent = 'Previous';
    prevLink.className = 'joinup-tabs-nav-link joinup-tabs-nav-prev';
    prevLink.addEventListener('click', function(e) {
      e.preventDefault();
      prevTab.click();
      updatePrevNextLinks(prevTab);
    });

    // Create Next link
    const nextLink = document.createElement('a');
    nextLink.href = nextTab.getAttribute('href');
    nextLink.textContent = 'Next';
    nextLink.className = 'btn btn-primary joinup-tabs-nav-link joinup-tabs-nav-next';
    nextLink.addEventListener('click', function(e) {
      e.preventDefault();
      nextTab.click();
      updatePrevNextLinks(nextTab);
    });

    // Prepend links to actionsElement
    actionsElement.prepend(nextLink);
    actionsElement.prepend(prevLink);

    // Scroll to the corresponding tab link.
    const targetElement = document.getElementById('edit-group-main');
    if (targetElement) {
      targetElement.scrollIntoView({ behavior: 'smooth' });
      // scroll further up according to the sticky header height.
      const headerHeight = document.querySelector('.sticky-header') ? document.querySelector('.sticky-header').offsetHeight : 0;
      window.scrollBy(0, -headerHeight);
    }
  }

  Drupal.behaviors.joinupSolutionTabsNavigation = {
    attach (context, settings) {
      const actionsElement = context.querySelector('#edit-actions');
      if (!actionsElement) {
        return;
      }

      // Get all the list items from the '.horizontal-tabs-list' element.
      const horizontalTabsList = context.querySelector('.horizontal-tabs-list');
      if (!horizontalTabsList) {
        return;
      }
      const listItems = horizontalTabsList.querySelectorAll('li');
      if (listItems.length === 0) {
        return;
      }

      // Check if there is already a selected tab from the fragment.
      const fragment = window.location.hash;
      let selectedTab = null;
      if (fragment) {
        selectedTab = horizontalTabsList.querySelector(`li a[href="${fragment}"]`);
      }
      else {
        // If no fragment, select the first tab by default.
        selectedTab = horizontalTabsList.querySelector('li a');
      }

      updatePrevNextLinks(selectedTab);

      // Integrate with the horizontal tabs to update the links if the tabs
      // are clicked manually.
      listItems.forEach((item) => {
        const tabLink = item.querySelector('a');
        // Smooth scroll to the tab content when clicked.
        tabLink.addEventListener('click', function (e) {
          e.preventDefault();
          const targetId = tabLink.getAttribute('href').substring(1);
          const targetElement = document.getElementById(targetId);
          if (targetElement) {
            targetElement.scrollIntoView({ behavior: 'smooth' });
          }
          // Update the selected tab and previous/next links.
          updatePrevNextLinks(tabLink);
        });
      });
    }
  };

}) (Drupal);
