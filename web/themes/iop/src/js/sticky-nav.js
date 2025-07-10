/**
 * @file
 * Adjust scroll-padding-top value with Drupal admin toolbal and .iop-navbar height.
 */
((Drupal, displace, once) => {
  Drupal.behaviors.adjustScrollPaddingTop = {
    attach(context) {
      once('adjust-scroll-padding', 'html', context).forEach(() => {
        const htmlEl = document.documentElement;
        const navbar = document.querySelector('.iop-navbar');

        const updateScrollPadding = () => {
          const toolbarOffset = displace?.offsets?.top || 0;
          const navbarHeight = navbar?.offsetHeight || 0;
          const extraPadding = 50; // Custom adjustment.
          const totalOffset = toolbarOffset + navbarHeight + extraPadding;

          htmlEl.style.scrollPaddingTop = `${totalOffset}px`;
        };

        const scheduleUpdate = () => {
          requestAnimationFrame(() => {
            requestAnimationFrame(updateScrollPadding);
          });
        };

        // Run after everything is fully loaded
        window.addEventListener('load', scheduleUpdate);

        // Re-run on admin bar changes or resize
        document.addEventListener('drupalViewportOffsetChange', scheduleUpdate);
        window.addEventListener('resize', scheduleUpdate);
      });
    }
  };
})(Drupal, Drupal.displace, once);
