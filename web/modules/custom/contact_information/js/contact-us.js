/**
 * @file
 * Opens a contact form dialog for users re-routed to EU Login.
 */

((Drupal, cookies) => {
  Drupal.behaviors.contactInformationDialog = {
    attach(context) {
      // Only interested in the document context.
      if (context !== document) {
        return;
      }
      const cookie = cookies.get("email_contact_info");
      // If there's no cookie, there's nothing more we can do.
      if (cookie === undefined) {
        return;
      }
      const contactInfoId = decodeURIComponent(cookie);
      const contactInfoElement = document.querySelector(
        `[data-joinup-contact-info-id="${contactInfoId}"]`,
      );
      // Does the contact information entity still exist?
      if (contactInfoElement) {
        const contactInfoLink = contactInfoElement.querySelector("a.use-ajax");
        if (contactInfoLink) {
          cookies.remove("email_contact_info");
          contactInfoLink.click();
        }
      }
      // Remove the cookie.
      cookies.remove("email_contact_info");
    },
  };
})(Drupal, window.Cookies);
