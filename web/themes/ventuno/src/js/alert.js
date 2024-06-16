/**
 * @file
 * Message template overrides.
 */

((Drupal, drupalSettings) => {
  /**
   * Overrides message theme function.
   *
   * @param {object} message
   *   The message object.
   * @param {string} message.text
   *   The message text.
   * @param {object} options
   *   The message context.
   * @param {string} options.type
   *   The message type.
   * @param {string} options.id
   *   ID of the message, for reference.
   *
   * @return {HTMLElement}
   *   A DOM Node.
   */
  Drupal.theme.message = ({ text }, { type, id }) => {
    const messagesTypes = Drupal.Message.getMessageTypeLabels();
    const messageWrapper = document.createElement("div");
    const messageLabel = messagesTypes[type]
      ? `<p class="visually-hidden">${messagesTypes[type]}</p>`
      : "";
    let icon;
    let variant;
    switch (type) {
      case "error":
        icon = "exclamation-triangle-fill";
        variant = "danger";
        break;

      case "warning":
        icon = "exclamation-circle-fill";
        variant = "warning";
        break;

      // Status.
      default:
        icon = "info-circle-fill";
        variant = "success";
    }

    messageWrapper.setAttribute(
      "class",
      `alert alert-${variant} d-flex align-items-center`
    );
    messageWrapper.setAttribute("aria-label", messagesTypes[type]);
    messageWrapper.setAttribute(
      "role",
      type === "error" ? "alert" : "contentinfo"
    );
    messageWrapper.setAttribute("data-drupal-message-id", id);
    messageWrapper.setAttribute("data-drupal-message-type", type);

    messageWrapper.innerHTML = `
      <svg class="flex-shrink-0 me-3 mt-1 align-self-start bi icon--s">
        <use xlink:href="${drupalSettings.bcl_icon_path}#${icon}"></use>
      </svg>
      <div class="alert-content flex-grow-1 text-dark">
        ${messageLabel}
        ${text}
      </div>
    `;

    return messageWrapper;
  };
})(Drupal, drupalSettings);
