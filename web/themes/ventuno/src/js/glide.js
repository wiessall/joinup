/**
 * @file
 * Glide slider configuration.
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
      const tabs = document.querySelectorAll(".explore-block button.nav-link");
      // Mount only the visible slide
      function mountSlide() {
        const slides = document.querySelectorAll(".tab-pane.active .glide");
        slides.forEach((slide) => {
          const glide = new Glide(slide, {
            type: "carousel",
            gap: 24,
            perView: 4,
            wrapperWidth: 300,
            breakpoints: {
              1199: { perView: 3 },
              991: { perView: 2 },
              767: { perView: 1 },
            },
          }).mount({ Controls, Breakpoints, Swipe });

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
