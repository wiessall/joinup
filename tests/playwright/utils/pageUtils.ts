/**
 * @file Page utilities.
 */

import { Page } from "@playwright/test";

/**
 * Wait until Webtools finishes processing.
 *
 * @param {Page} page - Playwright page instance.
 * @return {Promise<void>} - Resolves when Webtools is idle or missing.
 */
async function waitForWebtools(page: Page): Promise<void> {
  await page.waitForFunction(
    // @ts-expect-error $wt is injected by Webtools.
    () => typeof window.$wt === "undefined" || window.$wt.inProgress === false,
  );
}

export default {
  waitForWebtools,
};
