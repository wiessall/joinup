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
    const spritePath = drupalSettings.path.bclIconPath;
    const label = Drupal.t("Shared from @group", { "@group": groupLabel });
    return `<span title="${label}" class="ms-2"><svg class="bi icon--s"><use xlink:href="${spritePath}#arrow-repeat"></use></svg></span>`;
  };
})(drupalSettings);
