/**
 * @file
 * Glide slider configuration.
 */
import Glide, {
  Breakpoints,
  Controls,
  Keyboard,
  Swipe,
  // eslint-disable-next-line import/no-unresolved
} from "@glidejs/glide/dist/glide.modular.esm";

(() => {
  Drupal.behaviors.iopGlide = {
    attach() {
      // Mount only the visible slide
      function mountSlide() {
        const slides = document.querySelectorAll(".glide");
        slides.forEach((slide) => {
          const glide = new Glide(slide, {
            type: "carousel",
            focusAt: "center",
            gap: 56,
            perView: 3,
            breakpoints: {
              1399: { gap: 50 },
              1199: { gap: 44 },
              991: { gap: 36 },
              767: {
                gap: 16,
                perView: 1,
              },
            },
          });
          glide.mount({ Breakpoints, Controls, Keyboard, Swipe });
        });
      }
      mountSlide();
    },
  };
})();
