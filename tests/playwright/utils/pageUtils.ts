/**
 * @file Page utilities.
 */

import { Page } from "@playwright/test";

/**
 * Neutralize overlaying elements.
 *
 * @param {Page} page - Playwright page instance.
 */
async function neutralizeOverlayElements(page: Page): Promise<void> {
  await Promise.all([
    // Detach the sticky group header behavior to reset position:fixed and
    // inline height it sets on page load, which would corrupt screenshots.
    page.evaluate(() => {
      Drupal.behaviors.stickyGroupHeader?.detach(
        document,
        drupalSettings,
        "unload",
      );
    }),
    page.addStyleTag({
      content: `
        .joinup-navbar,
        .region-header__inner {
          position: initial !important;
        }
        .actions-menu {
          position: relative;
          left: calc(100% - 3rem);
          bottom: 1.5rem;
        }
        /* Hide the "for testing purposes only" notice that the Webtools banner
           only shows off *.europa.eu and that shifts the whole page down. */
        #globan .wt-globan--invalid-domain {
          display: none !important;
        }
      `,
    }),
  ]);
}

/**
 * Prepares and stabilizes the page before taking a screenshot.
 *
 * @param {Page} page - Playwright page instance.
 */
async function prepareForScreenshot(page: Page): Promise<void> {
  await neutralizeOverlayElements(page);
}

export default {
  neutralizeOverlayElements,
  prepareForScreenshot,
};
