/**
 * @file
 * Theme override for Drupal.theme.sharedContentCue.
 *
 * @see Drupal.theme.sharedContentCue
 */
((drupalSettings) => {
  /**
   * Theme function that renders a visual cue for shared content.
   *
   * @param {string} groupLabel
   *   The group label.
   *
   * @return {string}
   *   The HTML for the shared content visual cue.
   */
  Drupal.theme.sharedContentCue = (groupLabel) => {
    const spritePath = drupalSettings.bcl_icon_path;
    const label = Drupal.t("Shared from @group", { "@group": groupLabel });
    return `<span title="${label}"><svg class="bi icon--xs"><use xlink:href="${spritePath}#arrow-repeat"></use></svg></span>`;
  };
})(drupalSettings);
