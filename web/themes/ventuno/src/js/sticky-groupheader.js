/**
 * @file
 * Make group header sticky/fixed when scrolling.
 *
 * We use position fixed here because the height of the header gets resized and
 * position sticky doesn't deal well with this.
 * @see https://stackoverflow.com/questions/44567125/position-sticky-scroll-bouncing-when-combined-with-javascript-height-adjustme
 * @see https://issues.chromium.org/issues/41326022
 */
((Drupal, displace, once) => {
  Drupal.behaviors.stickyGroupHeader = {
    attach(context) {
      const groupHeader = once(
        "sticky-group-header",
        "#block-ventuno-groupheader",
        context
      );
      if (!groupHeader.length) {
        return;
      }

      // Get required regions.
      const regionHeader = context.querySelector(".region-header");
      const regionHeaderInner = regionHeader.querySelector(
        ".region-header__inner"
      );
      if (!regionHeader || !regionHeaderInner) {
        return;
      }

      // Init sticky behavior, acts on scroll event.
      const onScroll = () => {
        const scrollPosition =
          window.scrollY || document.documentElement.scrollTop;
        const isSticky =
          scrollPosition + displace.offsets.top > regionHeader.offsetTop;
        regionHeaderInner.classList.toggle("fixed-top", isSticky);
      };
      onScroll();
      window.addEventListener("scroll", onScroll);

      // Adjust scroll padding (when navigating to anchors).
      const html = context.querySelector("html");
      if (html) {
        const paddingTop = displace.offsets.top + regionHeader.offsetHeight;
        html.style.scrollPaddingTop = `${paddingTop}px`;
      }

      // Unset Joinup navbar sticky behavior.
      const joinupNavbar = context.querySelector(".joinup-navbar");
      if (joinupNavbar) {
        joinupNavbar.classList.remove("navbar--sticky");
        joinupNavbar.classList.add("position-static");
      }
    },
  };
})(Drupal, Drupal.displace, once);
