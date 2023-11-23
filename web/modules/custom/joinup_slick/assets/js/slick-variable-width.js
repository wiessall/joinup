/**
 * @file
 * Modifies non-infinite Slick carousels with variable width enabled.
 */
((Drupal, once, $) => {
  /**
   * Object storage and callbacks for Slick carousels if variable width enabled.
   *
   * @type {object}
   *
   * @prop {callback} calculateSlidesToShow
   *   Calculates the max number of slides to show.
   * @prop {callback} deferredRecalculate
   *   Schedules a recalculation (using calculateSlidesToShow).
   * @prop {object} timeouts
   *   Object storing recalculation timeouts of slick instances.
   */
  Drupal.joinupSlickVariableWidth = {
    calculateSlidesToShow(slickNode) {
      const $slick = $(slickNode);
      // Get width of the track.
      const width = $slick.find(".slick-list").width();
      // Find out how many of the last slides can fit to the available width.
      const slidesReversed = $slick.find(".slick__slide").get().reverse();
      let lastSlidesWidth = 0;
      let count = 0;
      while (lastSlidesWidth < width && slidesReversed[count]) {
        lastSlidesWidth += $(slidesReversed[count]).outerWidth();
        if (lastSlidesWidth < width) {
          count += 1;
        }
      }
      const prevCount = $slick.slick("slickGetOption", "slidesToShow");
      $slick.slick("slickSetOption", "slidesToShow", Math.max(count, 1));
      if (
        count > prevCount &&
        $slick.slick("slickCurrentSlide") > slidesReversed.length - count
      ) {
        $slick.slick("slickGoTo", slidesReversed.length - count);
      } else {
        $slick.slick("slickGoTo", $slick.slick("slickCurrentSlide"));
      }
      // All slides fit.
      if (count === slidesReversed.length && slickNode.slick) {
        $(slickNode.slick.$prevArrow).addClass("visually-hidden");
        $(slickNode.slick.$nextArrow).addClass("visually-hidden");
      }
    },
    deferredRecalculate(slickNode) {
      if (this.timeouts[slickNode.id]) {
        clearTimeout(this.timeouts[slickNode.id]);
      }
      this.timeouts[slickNode.id] = setTimeout(
        this.calculateSlidesToShow,
        100,
        slickNode
      );
    },
    timeouts: {},
  };

  /**
   * Custom control for Slick carousels if variable width enabled.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches the required handlers for sliders with variable width.
   */
  Drupal.behaviors.joinupSlickVariableWidth = {
    attach(context) {
      function initialize(slickNode) {
        // If this instance is infinite or has no variable with, don't do
        // anything.
        if (
          !slickNode.slick ||
          !slickNode.slick.options ||
          slickNode.slick.options.infinite ||
          !slickNode.slick.options.variableWidth
        ) {
          return;
        }

        Drupal.joinupSlickVariableWidth.calculateSlidesToShow(slickNode);

        // Event listener for window resize.
        $(window).on("resize", () => {
          Drupal.joinupSlickVariableWidth.deferredRecalculate(slickNode);
        });
        // Event listener for viewport size change (e.g. vertical toolbar open).
        $(document).on("drupalViewportOffsetChange", () => {
          Drupal.joinupSlickVariableWidth.deferredRecalculate(slickNode);
        });
      }
      once("joinupSlickVariableWidth", "[data-slick]", context).forEach(
        (slickNode) => {
          initialize(slickNode);
        }
      );
    },
  };
})(Drupal, once, jQuery);
