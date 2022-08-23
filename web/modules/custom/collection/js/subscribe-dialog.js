(($, Drupal, cookies, drupalSettings) => {
  // This is firing on document ready instead of using Drupal behaviors since
  // we only want to check this on initial page load, not on AJAX requests.
  $(document).ready(() => {
    // When an anonymous user indicates that they want to join a collection a
    // cookie will be set that tracks which collection the user wants to join,
    // so that we can show a dialog urging the user to subscribe to email
    // notifications.
    // If the cookie is set, do an AJAX request to show the subscribe form.
    const encodedCollectionId = cookies.get("join_group");
    if (encodedCollectionId) {
      try {
        const collectionId = decodeURIComponent(encodedCollectionId);
        if (collectionId === drupalSettings.joinGroupData.id) {
          const { sparqlEncodedId } = drupalSettings.joinGroupData;
          if (sparqlEncodedId) {
            Drupal.ajax({
              url: `${drupalSettings.path.baseUrl}ajax/collection/${sparqlEncodedId}/subscribe/auth`,
            }).execute();
          }
        }
      } catch (err) {
        // Collection ID was not URL encoded. Remove the invalid cookie.
        cookies.remove("join_group");
      }
    }
  });
})(jQuery, Drupal, window.Cookies, drupalSettings);
