/**
 * @file
 * Visual regression tests for the EIF perspective page.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("EIF perspective", () => {
  test("EIF perspective layout", async ({ page }) => {
    await page.goto(
      "/collection/portal-support/solution/information-sharing-protocols/eif-perspective",
    );
    await pageUtils.prepareForScreenshot(page);

    const perspective = page.locator(".solution__eif-perspective").first();
    await expect(perspective).toBeVisible();
    await expect(perspective).toHaveScreenshot("eif-perspective-page.png");
  });
});
