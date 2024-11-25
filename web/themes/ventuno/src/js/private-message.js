/**
 * @file
 * Show the notification with counter in the link for private messages page.
 */
((Drupal, once, $) => {
  Drupal.behaviors.privateMessage = {
    attach(context) {
      // Show the notification with counter in the link for private messages page.
      once("notification-counter", "html", context).forEach(() => {
        const myMessagesLink = context
          .querySelector("ul#main-navbar-list")
          ?.querySelector('a.dropdown-list-link[href="/user/messages"]');
        let myMessagesText = myMessagesLink?.innerHTML;
        if (myMessagesText === "") {
          return;
        }
        myMessagesText = myMessagesText.replace(/ \([0-9]+\)/g, "");

        $(document).on("ajaxSuccess", (event, xhr) => {
          const response = xhr.responseJSON[0];
          if (response?.command !== "privateMessageUpdateUnreadItemsCount") {
            return;
          }
          if (response.unreadItemsCount > 0) {
            myMessagesLink.innerHTML = `${myMessagesText}(${response.unreadItemsCount})`;
          } else {
            myMessagesLink.innerHTML = myMessagesText;
          }
        });
      });
    },
  };
})(Drupal, once, jQuery);
