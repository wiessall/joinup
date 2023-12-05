/**
 * @file
 * Glide slider configuration for carousel paragraphs.
 */
import Glide, {
  Controls,
  Breakpoints,
  Swipe,
  // eslint-disable-next-line import/no-unresolved
} from "@glidejs/glide/dist/glide.modular.esm";
import ControlsDisabler from "./helpers/glide.controlsDisabler.esm";

(() => {
  Drupal.behaviors.glide = {
    attach() {
      function mountSlide() {
        const slides = document.querySelectorAll(".paragraph--carousel .glide");
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

          glide.on('resize', function() {
            if (window.matchMedia('(min-width: 576px)').matches) {
              glide.update({startAt: 0, perView: 2, gap: 16}).disable();
            }
            if (window.matchMedia('(min-width: 768px)').matches) {
              glide.update({startAt: 0, perView: 3, gap: 16}).disable();
            } 
            if (window.matchMedia('(min-width: 1200px)').matches) {
              glide.update({startAt: 0, perView: 4, gap: 16}).disable();
            }
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
