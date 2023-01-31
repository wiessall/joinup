(function (factory) {
  typeof define === 'function' && define.amd ? define(factory) :
  factory();
})((function () { 'use strict';

  /**
   * @file
   * Theme override for Drupal.theme.checkbox.
   *
   * @see Drupal.theme.checkbox
   */
  (drupalSettings => {
    /**
     * Theme function that renders a visual cue for shared content.
     *
     * @param {string} groupLabel
     *   The group label.
     *
     * @param {string} spritePath
     *   The path where the sprite is located in theme.
     *
     * @return {string}
     *   The HTML for the shared content visual cue.
     */
    Drupal.theme.sharedContentCue = groupLabel => {
      const spritePath = drupalSettings.path.bclIconPath;
      const label = Drupal.t("Shared from @group", {
        "@group": groupLabel
      });
      return `<span title="${label}" class="ms-2"><svg class="bi icon--s"><use xlink:href="${spritePath}#arrow-repeat"></use></svg></span>`;
    };
  })(drupalSettings);

}));
//# sourceMappingURL=shared-entity.js.map
