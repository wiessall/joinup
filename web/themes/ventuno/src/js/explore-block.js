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

((Drupal, once) => {
  Drupal.behaviors.exploreBlock = {
    attach(context) {
      // Initializes the Glide slider.
      const initializeGlide = (glideElement) => {
        if (glideElement.classList.contains("glide-initialized")) {
          return;
        }

        const glideInstance = new Glide(glideElement, {
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

        glideInstance.update({ wrapperWidth: 300 });
        glideElement.classList.add("glide-initialized");
      };

      once("explore-block", ".explore-block", context).forEach(
        (exploreBlock) => {
          // Init the slider for the active tab.
          const activeTab = exploreBlock.querySelector(
            ".tab-pane.active .glide",
          );
          if (activeTab) initializeGlide(activeTab);

          // Listen for tabs becoming active and initialize Glide.
          // Needed for Glide to accurately calculate the available space/width.
          const tabs = exploreBlock.querySelectorAll(".nav-link");
          tabs.forEach((tabLink) => {
            const target = tabLink.getAttribute("data-bs-target");
            const tabPane = exploreBlock.querySelector(target);

            tabLink.addEventListener("shown.bs.tab", () => {
              const glide = tabPane.querySelector(".glide");
              if (glide) initializeGlide(glide);
            });
          });
        },
      );
    },
  };
})(Drupal, once);
