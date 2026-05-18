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
      this.isSticky = false;
      this.observer = null;
    }

    init() {
      // Ensure required elements are present.
      if (!this.navbar || !this.header || !this.headerInner || !this.html) {
        return;
      }

      // Unset navbar sticky behavior.
      this.navbar.classList.remove("navbar--sticky");
      this.navbar.classList.add("position-static");

      // Init the observer.
      this.updateObserver();

      // Set initial scroll padding to account for header and toolbar
      this.updateScrollPadding();

      // Refresh the observer when Drupal.displace offsets change, for new
      // rootMargin's to take effect.
      $(this.context).on("drupalViewportOffsetChange", () => {
        this.updateObserver();
        // Update scroll padding when toolbar changes
        this.updateScrollPadding();
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

        // 2. Add/remove fixed position class on headerInner.
        this.headerInner.classList.toggle("fixed-top", goSticky);

        // 3. Update current state.
        this.isSticky = nowSticky;

        // 4. Update scroll padding to account for any height changes
        this.updateScrollPadding();
      }
    }

    /**
     * Updates scroll padding to always account for both:
     * - Drupal toolbar height (displace.offsets.top)
     * - Header inner height (regardless of sticky state)
     * This ensures anchor links are always positioned below both elements.
     */
    updateScrollPadding() {
      // Always include both toolbar offset and header height
      const offset = displace.offsets.top + this.headerInner.offsetHeight + 50;

      // Apply to root element
      this.html.style.scrollPaddingTop = `${offset}px`;
    }

    /**
     * Tears down the sticky behavior and resets all modified state.
     */
    destroy() {
      if (this.observer) {
        this.observer.disconnect();
        this.observer = null;
      }
      if (this.header) {
        this.header.style.height = null;
      }
      if (this.headerInner) {
        this.headerInner.classList.remove("fixed-top");
      }
      if (this.html) {
        this.html.style.scrollPaddingTop = null;
      }
      this.isSticky = false;
    }
  }

  Drupal.behaviors.stickyGroupHeader = {
    attach(context) {
      once("sticky-group-header", SELECTORS.GROUP_HEADER, context).forEach(
        (el) => {
          const stickyHeader = new StickyGroupHeader(context);
          stickyHeader.init();
          // Store instance on element so detach() can reach it.
          el.stickyGroupHeader = stickyHeader;
        },
      );
    },
    detach(context, settings, trigger) {
      if (trigger !== "unload") {
        return;
      }
      once
        .remove("sticky-group-header", SELECTORS.GROUP_HEADER, context)
        .forEach((el) => {
          el.stickyGroupHeader?.destroy();
          el.stickyGroupHeader = null;
        });
    },
  };
})(Drupal, Drupal.displace, once, jQuery);
