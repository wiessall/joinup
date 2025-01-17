/**
 * @file
 * Glide slider configuration.
 */
import Glide, {
  Breakpoints,
  Controls,
  Keyboard,
  Swipe,
  // It is impossible to build this script with BLC-builder without the file
  // extension since Glide 3.7.0+
  // eslint-disable-next-line import/extensions
} from "@glidejs/glide/dist/glide.modular.esm.js";

((Drupal, once) => {
  Drupal.behaviors.iopGlide = {
    attach(context) {
      once("iop-glide", ".glide", context).forEach((slider) => {
        const glide = new Glide(slider, {
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
    },
  };
})(Drupal, once);
