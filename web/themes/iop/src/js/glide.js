/**
 * @file
 * Glide slider configuration.
 */
import Glide, { Controls, Swipe } from "@glidejs/glide/dist/glide.modular.esm";

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
          });
          glide.mount({ Controls, Swipe });
          // Right before movement transition begins, add the transitioning classes to the element that is going to be active
          // glide.on("move", () => {
          //   const active = slide.querySelector(".glide__slide--active");
          //   active.classList.add("glide__slide--scaledown");
          //   active.nextElementSibling.classList.add("glide__slide--scaleup");
          // });
          // // Right after movement transition ends, remove the transitioning scaling classes to the activated element.
          // glide.on("move.after", () => {
          //   const active = slide.querySelector(".glide__slide--active");
          //   active.classList.remove("glide__slide--scaleup");
          //   active.previousElementSibling.classList.remove(
          //     "glide__slide--scaledown"
          //   );
          // });
        });
      }
      mountSlide();
    },
  };
})();
