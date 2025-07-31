/**
 * @file
 * Generic OE Popover functionality.
 */

(function (Drupal, once) {
  'use strict';

  /**
   * Initialize generic popovers.
   */
  Drupal.behaviors.oePopover = {
    attach: function (context, settings) {
      const triggers = once('oe-popover', '.oe-popover__trigger', context);

      triggers.forEach(function (trigger) {
        const popover = trigger.closest('.oe-popover');
        const targetId = trigger.getAttribute('data-popover-target');
        const content = document.getElementById(targetId);

        if (!content) {
          return;
        }

        // Initialize Bootstrap popover
        const bsPopover = new bootstrap.Popover(trigger, {
          content: content.innerHTML,
          html: true,
          placement: trigger.getAttribute('data-bs-placement') || 'bottom',
          trigger: 'click',
          customClass: 'oe-popover-component',
          boundary: 'viewport',
          fallbackPlacements: ['top', 'left', 'right']
        });

        // Close on outside click
        document.addEventListener('click', function (event) {
          if (!popover.contains(event.target) &&
              !event.target.closest('.popover.oe-popover-component')) {
            bsPopover.hide();
          }
        });

        // Close on Escape key
        trigger.addEventListener('keydown', function (event) {
          if (event.key === 'Escape') {
            bsPopover.hide();
          }
        });
      });
    }
  };

})(Drupal, once);
