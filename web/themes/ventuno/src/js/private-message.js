/**
 * @file
 * Show the notification with counter in the link for private messages page.
 * Hides the inbox toggle button if the inbox container is empty.
 * Adds aria-labels for better accessibility.
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
        if (!myMessagesText || myMessagesText === "") {
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

      // Hide toggle if .private-message-thread--full-container is empty.
      once(
        "hide-empty-inbox-toggle",
        ".private-messages-inbox-wrapper",
        context,
      ).forEach((wrapper) => {
        const toggle = wrapper.querySelector(".private-messages-toggle-inbox");
        const threadContainer = wrapper.querySelector(
          ".private-message-thread--full-container",
        );

        if (threadContainer && threadContainer.matches(":empty")) {
          toggle?.classList.add("hidden-toggle");
        }
      });

      // Add aria-label to thread links for accessibility.
      once(
        "thread-link-aria-label",
        ".private-message-inbox-thread-link",
        context,
      ).forEach((link) => {
        link.setAttribute("aria-label", Drupal.t("Read thread"));
      });
    },
  };
})(Drupal, once, jQuery);
