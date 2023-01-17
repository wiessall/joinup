/**
 * @file
 * Add current path into contact information wysiwyg.
 */

((Drupal, once, drupalSettings) => {
  Drupal.behaviors.contactInformationWysiwyg = {
    attach(context) {
      once(
        "myContactInformation",
        "a[data-joinup-contact-information]",
        context
      ).forEach((element) => {
        const { path } = drupalSettings;
        element.href += path.currentPath;
      });
    },
  };
})(Drupal, once, drupalSettings);
