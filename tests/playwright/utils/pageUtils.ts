/**
 * @file Page utilities.
 */

import { Page } from "@playwright/test";

/**
 * Wait until Webtools finishes processing.
 *
 * @param {Page} page - Playwright page instance.
 */
async function waitForWebtools(page: Page): Promise<void> {
  await page.waitForFunction(
    // @ts-expect-error $wt is injected by Webtools.
    () => typeof window.$wt === "undefined" || window.$wt.inProgress === false,
  );
}

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
  await Promise.all([
    // Some elements on the page (i.a. translate page button) rely on webtools to be done processing.
    waitForWebtools(page),
    // Prevent overlaying elements from being part of snapshots.
    neutralizeOverlayElements(page),
  ]);
}

export default {
  waitForWebtools,
  neutralizeOverlayElements,
  prepareForScreenshot,
};
