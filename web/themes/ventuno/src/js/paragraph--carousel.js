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

(() => {
  Drupal.behaviors.glide = {
    attach() {
      function mountSlide() {
        const slides = document.querySelectorAll(".paragraph--carousel .glide");
        slides.forEach((slide) => {
          const glide = new Glide(slide, {
            type: "carousel",
            gap: 16,
            perView: 4,
            breakpoints: {
              1199: { perView: 3 },
              767: { perView: 2 },
              575: { perView: 1 },
            },
          });

          glide.mount({ Controls, Breakpoints, Swipe });
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
