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
  await page.addStyleTag({
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
    `,
  });
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
