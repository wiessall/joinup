/**
 * @file
 * Add current path into contact information wysiwyg.
 */

((Drupal, once, drupalSettings) => {
  Drupal.behaviors.contactInformationWysiwyg = {
    attach(context) {
      once(
        "myContactInformation",
        "a[data-joinup-contact-information-href]",
        context
      ).forEach((element) => {
        let href;
        if (drupalSettings.user.uid !== 0) {
          href = element.getAttribute("data-joinup-contact-information-href");
          href += drupalSettings.path.currentPath;
        } else {
          href = element.getAttribute("data-joinup-contact-information-login");
          href += `?destination=${drupalSettings.path.currentPath}`;
        }
        element.removeAttribute("data-joinup-contact-information-href");
        element.removeAttribute("data-joinup-contact-information-login");
        element.href = href;
      });
    },
  };
})(Drupal, once, drupalSettings);
