/**
 * @file
 * Glide slider configuration for OSS Most recent solutions block.
 */
import Glide, {
  Controls,
  Breakpoints,
  Swipe,
  // eslint-disable-next-line import/no-unresolved
} from "@glidejs/glide/dist/glide.modular.esm";
import ControlsDisabler from "./helpers/glide.controlsDisabler.esm";

(() => {
  Drupal.behaviors.ossGlide = {
    attach() {
      function mountSlide() {
        const slides = document.querySelectorAll(
          ".block-views-blockoss-solution-oss-most-recent-solutions .glide"
        );
        slides.forEach((slide) => {
          const glide = new Glide(slide, {
            rewind: false,
            bound: true,
            dragThreshold: false,
            gap: 16,
            perView: 4,
            breakpoints: {
              1199: { perView: 3 },
              767: { perView: 2 },
              575: { perView: 1 },
            },
          });

          glide.mount({ Controls, Breakpoints, Swipe, ControlsDisabler });
          const forward = slide.querySelector(".glide__arrow--right");
          const backward = slide.querySelector(".glide__arrow--left");

          forward.addEventListener("click", () => {
            glide.go(">");
          });

          backward.addEventListener("click", () => {
            glide.go("<");
          });
        });
      }
      mountSlide();
    },
  };
})();
