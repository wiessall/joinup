/**
 * @file
 * Collection homepage components.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("Collection homepage", () => {
  test("Collection homepage components", async ({ page }) => {
    await page.goto("/collection/portal-support");
    await pageUtils.prepareForScreenshot(page);

    const navbar = page.locator("header.joinup-navbar");
    await expect(navbar).toBeVisible();
    await expect(navbar).toHaveScreenshot("page-header.png");

    const groupHeader = page.locator(".region-header");
    await expect(groupHeader).toBeVisible();
    await expect(groupHeader).toHaveScreenshot("group-header.png");

    const contentTiles = page.locator(".collection__collection-settings .row");
    await expect(contentTiles).toBeVisible();
    await expect(contentTiles).toHaveScreenshot("collection-content-tiles.png");

    const actionButtons = page.locator(".content .region-content-top");
    await expect(actionButtons).toBeVisible();
    await expect(actionButtons).toHaveScreenshot("action-buttons-on-top.png");
  });
});
