/**
 * @file
 * Adds a wrapper around the table to prevent it overflowing to the right on
 * small screens.
 */

((Drupal, once) => {
  Drupal.behaviors.paragraphImgInline = {
    attach(context) {
      const img = once(
        "paragraph-img-fluid",
        document.querySelectorAll("img.img-fluid"),
        context,
      );
      img.forEach((element) => {
        const parentDiv = element.closest("div");
        if (parentDiv && parentDiv.className.trim() === "") {
          parentDiv.classList.add("inline-block");
        }
      });
    },
  };
})(Drupal, once);
