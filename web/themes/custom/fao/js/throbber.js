/**
 * Smart Throbber for FAO STI Portal
 * Shows loading overlay when filters/facets are changed
 */

(function () {
  'use strict';

  var throbberActive = false;

  function showSmartThrobber(isReset) {
    if (throbberActive) return;

    var existing = document.getElementById('smart-throbber');
    if (existing) {
      existing.remove();
    }

    var message = isReset ? 'Resetting search...' : 'Loading results...';

    var container = document.createElement('div');
    container.id = 'smart-throbber';
    container.innerHTML = `
      <div style="
        width: 60px;
        height: 60px;
        margin: 0 auto 15px;
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid #fff;
        border-radius: 50%;
        animation: throbber-spin 1s linear infinite;
      "></div>
      <div style="
        font-family: Arial, sans-serif;
        font-size: 14px;
        color: #fff;
        line-height: 1.4;
        text-align: center;
      ">${message}</div>
    `;
    container.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      background: rgba(0, 0, 0, 0.35);
    `;

    // Prevent scrolling while throbber is active
    document.body.style.overflow = 'hidden';

    document.body.appendChild(container);
    throbberActive = true;
  }

  function hideThrobber() {
    var throbber = document.getElementById('smart-throbber');
    if (throbber) {
      throbber.remove();
      throbberActive = false;
      document.body.style.overflow = '';
    }
  }

  function isClearingAction(form) {
    var hasAnyFilters = false;

    // Check text inputs
    var textInputs = form.querySelectorAll('input[type="text"], input[type="search"]');
    for (var i = 0; i < textInputs.length; i++) {
      if (textInputs[i].value && textInputs[i].value.trim() !== '') {
        hasAnyFilters = true;
      }
    }

    // Check checkboxes
    var checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
    if (checkedBoxes.length > 0) {
      hasAnyFilters = true;
    }

    // Check select dropdowns
    var selects = form.querySelectorAll('select');
    for (var i = 0; i < selects.length; i++) {
      var select = selects[i];
      var value = select.value;

      if (value &&
          value !== '' &&
          value !== 'All' &&
          value !== select.options[0].value) {
        hasAnyFilters = true;
      }
    }

    // Check radio buttons
    var checkedRadios = form.querySelectorAll('input[type="radio"]:checked');
    for (var i = 0; i < checkedRadios.length; i++) {
      var radio = checkedRadios[i];
      if (radio.value && radio.value !== '' && radio.value !== 'All') {
        hasAnyFilters = true;
      }
    }

    return !hasAnyFilters;
  }

  // Watch for form submissions
  document.addEventListener('submit', function(e) {
    var form = e.target;

    // Check if this is a search/exposed form
    var isSearchForm = form.classList.contains('views-exposed-form') ||
                       form.id.includes('search') ||
                       form.className.includes('search') ||
                       form.querySelectorAll('input[type="search"], input[name*="search"]').length > 0;

    if (!isSearchForm) return;

    var isReset = false;
    var activeEl = document.activeElement;

    // Check for explicit reset button
    if (activeEl && (activeEl.type === 'reset' || activeEl.value === 'Reset' || activeEl.textContent === 'Reset')) {
      isReset = true;
    }

    // Check if clearing (no active filters)
    if (!isReset && isClearingAction(form)) {
      isReset = true;
    }

    showSmartThrobber(isReset);

    // Hide after appropriate delay
    var hideDelay = isReset ? 3000 : 10000; // 3s for reset, 10s for search

    setTimeout(function() {
      hideThrobber();
    }, hideDelay);
  });

  // Watch for Chosen dropdown changes (facets)
  document.addEventListener('change', function(e) {
    var target = e.target;

    // Check if this is a facet dropdown or chosen select
    var isFacetWidget = target.closest('.facets-widget-checkbox, .facets-widget-dropdown, .facet-item select');
    var isChosenSelect = target.closest('.chosen-select, .chosen-container select');

    if (!isFacetWidget && !isChosenSelect) return;

    showSmartThrobber(false);

    // Hide after 10 seconds
    setTimeout(function() {
      hideThrobber();
    }, 10000);
  }, true);

  // Watch for facet link clicks, radio buttons, and submit buttons
  document.addEventListener('click', function(e) {
    var target = e.target;

    // Check if this is a facet link
    var isFacetLink = target.closest('.facet-item a, .facets-item a, a.facet-item');
    var isChosenItem = target.closest('.chosen-results li, .chosen-single, .search-choice-close');

    // Check if this is a radio button (facet filter)
    var isRadioButton = target.tagName === 'INPUT' && target.type === 'radio';

    // Check if this is a submit button (or icon inside it)
    var isSubmitButton = target.closest('button[type="submit"], input[type="submit"]') ||
                          (target.tagName === 'I' && target.closest('button[type="submit"]'));

    if (!isFacetLink && !isChosenItem && !isRadioButton && !isSubmitButton) return;

    showSmartThrobber(false);

    // Hide after 10 seconds
    setTimeout(function() {
      hideThrobber();
    }, 10000);
  });

  // Watch for Enter key on search text inputs
  document.addEventListener('keydown', function(e) {
    if (e.key !== 'Enter') return;

    var target = e.target;
    var isTextInput = target.tagName === 'INPUT' && (target.type === 'text' || target.type === 'search');

    if (!isTextInput) return;

    // Check if this input is in a search/exposed form
    var form = target.closest('form');
    if (!form) return;

    var isSearchForm = form.classList.contains('views-exposed-form') ||
                         form.id.includes('search') ||
                       form.className.includes('search') ||
                       form.querySelectorAll('input[type="search"], input[name*="search"]').length > 0;

    if (!isSearchForm) return;

    showSmartThrobber(false);

    // Hide after 10 seconds
    setTimeout(function() {
      hideThrobber();
    }, 10000);
  });

  // Also watch for AJAX completion to hide earlier if possible
  if (typeof jQuery !== 'undefined') {
    jQuery(document).ajaxComplete(function() {
      setTimeout(function() {
        hideThrobber();
      }, 500);
    });
  }

  // Global access
  window.showThrobber = function() { showSmartThrobber(false); };
  window.hideThrobber = hideThrobber;

})();

// Keyframe animation for spinner
var style = document.createElement('style');
style.textContent = `
  @keyframes throbber-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
  }
`;
document.head.appendChild(style);
