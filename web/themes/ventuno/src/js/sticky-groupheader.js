/**
 * @file
 * Make group header sticky when scrolling.
 */
// prettier-ignore
(function (Drupal, displace, once) {
  Drupal.behaviors.stickyGroupHeader = {
    attach(context) {
      const groupHeader = once("sticky-group-header", "#block-ventuno-groupheader", context);
      if (!groupHeader.length) {
        return;
      }

      // The region-header is used to apply sticky positioning,
      const regionHeader = context.querySelector(".region-header");
      if (!regionHeader) {
        return;
      }

      // Set fixed height for the sticky div.
      regionHeader.style.height = `${regionHeader.offsetHeight}px`;

      // Insert placeholder div to determine initial position.
      const regionHeaderPlaceholder = document.createElement("div");
      regionHeaderPlaceholder.classList.add("region-header-placeholder");
      regionHeader.parentNode.insertBefore(regionHeaderPlaceholder, regionHeader);

      // Init sticky behavior, acts on scroll event.
      const onScroll = () => {
        const scrollPosition = window.scrollY || document.documentElement.scrollTop;
        const isSticky = (scrollPosition + displace.offsets.top) > regionHeaderPlaceholder.offsetTop;
        regionHeader.classList.toggle("sticky-top", isSticky);
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
