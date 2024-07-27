/**
 * @file
 * Makes group header sticky/fixed when scrolling.
 *
 * Due to issues with dynamic height changes, position sticky is not suitable.
 * @see https://stackoverflow.com/questions/44567125/position-sticky-scroll-bouncing-when-combined-with-javascript-height-adjustme
 * @see https://issues.chromium.org/issues/41326022
 *
 * Instead, we use position fixed and an intersection observer that detects when
 * to apply it to the header. When applied, an outer div gets a fixed height
 * assigned to prevent sudden jumps.
 */
((Drupal, displace, once, $) => {
  // Constants for selectors.
  const SELECTORS = {
    GROUP_HEADER: "#block-ventuno-groupheader",
    HEADER: ".region-header",
    HEADER_INNER: ".region-header__inner",
    NAVBAR: ".joinup-navbar",
    HTML: "html",
  };

  class StickyGroupHeader {
    constructor(context) {
      this.context = context;
      this.navbar = context.querySelector(SELECTORS.NAVBAR);
      this.header = context.querySelector(SELECTORS.HEADER);
      this.headerInner = context.querySelector(SELECTORS.HEADER_INNER);
      this.html = context.querySelector(SELECTORS.HTML);
      this.scrollPT = 0;
      this.isSticky = false;
      this.observer = null;
    }

    init() {
      // Ensure required elements are present.
      if (!this.navbar || !this.header || !this.headerInner || !this.html) {
        return;
      }

      // Init scrollPT.
      this.scrollPT = parseInt(this.html.style.scrollPaddingTop, 10) || 0;

      // Unset navbar sticky behavior.
      this.navbar.classList.remove("navbar--sticky");
      this.navbar.classList.add("position-static");

      // Init the observer.
      this.updateObserver();

      // Refresh the observer when Drupal.displace offsets change, for new
      // rootMargin's to take effect.
      $(this.context).on("drupalViewportOffsetChange", () => {
        this.updateObserver();
      });
    }

    /**
     * Init/update the intersection observer.
     */
    updateObserver() {
      // Disconnect and destroy existing observer, if exists.
      if (this.observer) {
        this.observer.disconnect();
        this.observer = null;
      }

      // Observe intersection events with the navbar.
      this.observer = new IntersectionObserver(
        (entries) => {
          entries.forEach((entry) => {
            this.onIntersect(!entry.isIntersecting);
          });
        },
        {
          // Account for the toolbar (set negative margins).
          rootMargin: `-${displace.offsets.top}px 0px 0px -${displace.offsets.left}px`,
        },
      );
      this.observer.observe(this.navbar);
    }

    /**
     * Handles intersection events from the observer.
     *
     * @param {boolean} nowSticky
     *   Whether the header should become sticky.
     */
    onIntersect(nowSticky) {
      // Act when we have a transition.
      const isTransitioning = this.isSticky !== nowSticky;
      if (isTransitioning) {
        const goSticky = !this.isSticky && nowSticky;
        const height = this.headerInner.offsetHeight;

        // 1. Set/unset fixed height on the header.
        this.header.style.height = goSticky ? `${height}px` : null;

        // 2. Adjust scroll padding (for navigating to anchors).
        const offset = goSticky ? displace.offsets.top + height : 0;
        this.html.style.scrollPaddingTop = `${this.scrollPT + offset}px`;

        // 3. Add/remove fixed position class on headerInner.
        this.headerInner.classList.toggle("fixed-top", goSticky);

        // 4. Update current state.
        this.isSticky = nowSticky;
      }
    }
  }

  Drupal.behaviors.stickyGroupHeader = {
    attach(context) {
      once("sticky-group-header", SELECTORS.GROUP_HEADER, context).forEach(
        () => {
          const stickyHeader = new StickyGroupHeader(context);
          stickyHeader.init();
        },
      );
    },
  };
})(Drupal, Drupal.displace, once, jQuery);
