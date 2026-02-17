/**
 * @file
 * Collection members page components.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("Collection members page", () => {
  test("Members page components", async ({ page }) => {
    await page.goto("/collection/portal-support/members");
    await pageUtils.prepareForScreenshot(page);

    await expect(
      page.locator("#views-exposed-form-members-overview-default"),
    ).toHaveScreenshot("block-ventuno-content.png");
  });
});
