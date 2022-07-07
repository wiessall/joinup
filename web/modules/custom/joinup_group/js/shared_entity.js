/**
 * @file
 * Attaches the behaviours to show visual cues on shared community content.
 */

((Drupal) => {
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
    const label = Drupal.t("Shared from @group", { "@group": groupLabel });
    return `<span title="${label}"></span>`;
  };

  /**
   * Adds visual cues for content shared from a collection to another.
   *
   * @type {Drupal~behavior}
   *
   * @prop {Drupal~behaviorAttach} attach
   *   Attaches shared content behaviors.
   */
  Drupal.behaviors.sharedContent = {
    attach: (context) => {
      const groupElement = document.querySelector(
        "[data-drupal-group-context]"
      );

      // If there is no global group context, bail out.
      if (groupElement === null) {
        return;
      }

      const groupId = groupElement.dataset.drupalGroupContext;
      if (!groupId) {
        return;
      }

      // context.querySelector()
      once("shared-cue", "[data-drupal-parent-id]", context).forEach(
        (groupContent) => {
          const parentId = groupContent.dataset.drupalParentId;

          // If it's in the current global collection context, don't add any cue.
          if (parentId === groupId) {
            return;
          }

          const statsContainer = groupContent.querySelector(
            "[data-drupal-card-stats]"
          );
          // There's no stats container defined in this card.
          if (statsContainer === null) {
            return;
          }

          const parentLabel = groupContent.dataset.drupalParentLabel;
          const sharedContentCue = document.createElement("div");
          sharedContentCue.setAttribute(
            "data-drupal-card-stats-shared",
            parentLabel
          );
          const template = document.createElement("template");
          template.innerHTML = Drupal.theme(
            "sharedContentCue",
            parentLabel
          ).trim();
          sharedContentCue.appendChild(template.content.firstChild);
          statsContainer.appendChild(sharedContentCue);
        }
      );
    },
  };
})(Drupal);
