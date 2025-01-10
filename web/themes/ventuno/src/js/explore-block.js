/**
 * @file
 * Glide slider configuration for explore block.
 */
import Glide, {
  Controls,
  Breakpoints,
  Swipe,
  // It is impossible to build this script with BLC-builder without the file
  // extension since Glide 3.7.0+
  // eslint-disable-next-line import/extensions
} from "@glidejs/glide/dist/glide.modular.esm.js";
import ControlsDisabler from "./helpers/glide.controlsDisabler.esm";

(() => {
  Drupal.behaviors.glide = {
    attach() {
      const tabs = document.querySelectorAll(".explore-block button.nav-link");
      // Mount only the visible slide
      function mountSlide() {
        const slides = document.querySelectorAll(".tab-pane.active .glide");
        slides.forEach((slide) => {
          const glide = new Glide(slide, {
            rewind: false,
            bound: true,
            dragThreshold: false,
            gap: 24,
            perView: 4,
            wrapperWidth: 300,
            breakpoints: {
              1199: { perView: 3 },
              991: { perView: 2 },
              767: { perView: 1 },
            },
          }).mount({ Controls, Breakpoints, Swipe, ControlsDisabler });

          glide.update({ wrapperWidth: 300 });
        });
      }
      // when switching tabs, mount visible slide again (otherwise slides are buggy)
      for (let i = 0; i < tabs.length; i++) {
        tabs[i].addEventListener("click", () => {
          mountSlide();
        });
      }
      mountSlide();
    },
  };
})();
