/**
 * @param Drupal
 * @param once
 * @param cookies
 * @file
 * Opens a contact form dialog for users re-routed to EU Login.
 */

((Drupal, once, cookies) => {
  Drupal.behaviors.contactInformationDialog = {
    attach() {
      // We only want to check this on initial page load.
      if (!once("contact-information-dialog", "html").length) {
        return;
      }

      // If there's no cookie, there's nothing more we can do.
      const cookie = cookies.get("email_contact_info");
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
})(Drupal, once, window.Cookies);
