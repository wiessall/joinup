/**
 * Counter Component Animation
 * Animates numbers when the counter comes into view
 */

(function (Drupal) {
  'use strict';

  Drupal.behaviors.counterAnimation = {
    attach: function (context, settings) {
      const counters = context.querySelectorAll('.card .card-title[data-counter]');

      const animateCounter = (element) => {
        const target = element.textContent.trim();

        // Check for explicit suffix in data attribute first
        let suffix = element.dataset.suffix || '';

        // Extract numeric value and any suffix (e.g., "50+", "1,234", "100K")
        // Match digits with optional commas, followed by any remaining characters
        const match = target.match(/([\d,.\s]+)(.*)/);
        if (!match) return;

        const numericPart = match[1].replace(/[,\s]/g, '');

        // If no data-suffix, use extracted suffix from text
        if (!suffix) {
          suffix = match[2].trim();
        }

        const targetValue = parseFloat(numericPart);

        if (isNaN(targetValue) || targetValue === 0) return;

        const duration = 4000; // 4 seconds
        const steps = 70;
        const increment = targetValue / steps;
        const stepDuration = duration / steps;
        let current = 0;

        element.textContent = '0' + suffix;

        const timer = setInterval(() => {
          current += increment;
          if (current >= targetValue) {
            current = targetValue;
            clearInterval(timer);
          }

          // Format with European style (dots as thousand separators)
          const formattedValue = Math.floor(current).toLocaleString('de-DE');
          element.textContent = formattedValue + suffix;
        }, stepDuration);
      };

      // Use Intersection Observer to trigger animation when visible
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && !entry.target.dataset.animated) {
            entry.target.dataset.animated = 'true';
            animateCounter(entry.target);
          }
        });
      }, {
        threshold: 0.5
      });

      counters.forEach((counter) => {
        if (!counter.dataset.animated) {
          observer.observe(counter);
        }
      });
    }
  };

})(Drupal);
