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

((Drupal, once) => {
  Drupal.behaviors.glide = {
    attach(context) {
      once("glide", ".carousel .glide", context).forEach((slide) => {
        const glide = new Glide(slide, {
          rewind: false,
          bound: true,
          dragThreshold: false,
          gap: 16,
          perView: 4,
          breakpoints: {
            9999: { perView: 4 },
            1199: { perView: 3 },
            767: { perView: 2 },
            575: { perView: 1 },
          },
        });

        let resetTimeoutId;
        const resetHandler = (glideSlider) => {
          if (glideSlider.settings.perView === glideSlider.previousPerView) {
            return;
          }
          glideSlider.previousPerView = glideSlider.settings.perView;
          glideSlider.enable().go("<<").disable();
        };
        glide.on("resize", () => {
          if (resetTimeoutId) {
            clearTimeout(resetTimeoutId);
          }
          resetTimeoutId = setTimeout(resetHandler, 50, glide);
        });

        glide.mount({ Controls, Breakpoints, Swipe, ControlsDisabler });
        glide.previousPerView = glide.settings.perView;
        const forward = slide.querySelector(".glide__arrow--right");
        const backward = slide.querySelector(".glide__arrow--left");

        forward.addEventListener("click", () => {
          glide.go(">");
        });

        backward.addEventListener("click", () => {
          glide.go("<");
        });
      });
    },
  };
})(Drupal, once);
