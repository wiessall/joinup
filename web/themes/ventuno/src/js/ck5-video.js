/**
 * @file
 * Add half-width in videos.
 */

((Drupal, once) => {
  Drupal.behaviors.ck5Video = {
    attach(context) {
      const video = once("div-ck5-video", ".js-ck5-video", context);
      video.forEach((element) => {
        const iframe = element.querySelector("iframe");
        if (iframe.width) {
          const half = iframe.width / 2;
          element.style.width = `${half}px`;
        }
      });
    },
  };
})(Drupal, once);
