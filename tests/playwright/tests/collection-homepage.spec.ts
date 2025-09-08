/**
 * @file
 * Collection homepage components.
 */

import { test, expect } from "@playwright/test";
import { waitForWebtools } from "../utils/functions";

test.describe("Collection homepage", () => {
  test("Collection homepage components", async ({ page }) => {
    await page.goto("/collection/portal");
    await waitForWebtools(page);

    await expect(page.locator("header.joinup-navbar")).toHaveScreenshot(
      "page-header.png",
    );
    await expect(page.locator(".region-header")).toHaveScreenshot(
      "group-header.png",
    );
    await expect(
      page.locator(".collection__collection-settings .row"),
    ).toHaveScreenshot("collection-content-tiles.png");
    await expect(page.locator(".content .region-content-top")).toHaveScreenshot(
      "action-buttons-on-top.png",
    );
  });
});
