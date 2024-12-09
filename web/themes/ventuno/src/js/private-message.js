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
          if (
            typeof xhr.responseJSON !== "undefined" &&
            typeof xhr.responseJSON[0] !== "undefined" &&
            xhr.responseJSON[0]?.command ===
              "privateMessageUpdateUnreadItemsCount"
          ) {
            if (xhr.responseJSON[0].unreadItemsCount > 0) {
              myMessagesLink.innerHTML = `${myMessagesText}(${xhr.responseJSON[0].unreadItemsCount})`;
            } else {
              myMessagesLink.innerHTML = myMessagesText;
            }
          }
        });
      });
    },
  };
})(Drupal, once, jQuery);
