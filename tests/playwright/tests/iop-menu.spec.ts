/**
 * @file
 * Group navigation menu of the Interoperable Europe theme.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("IOP theme", () => {
  test("Group navigation menu", async ({ page }) => {
    await page.goto("/interoperable-europe");
    await pageUtils.prepareForScreenshot(page);

    const navigation = page.locator("#block-iope-navigation");
    await expect(navigation).toBeVisible();

    // Below the 'md' breakpoint the menu is collapsed behind the toggler, so
    // expand it to bring the menu items themselves into the screenshot.
    const toggler = navigation.locator(".navbar-toggler");
    if (await toggler.isVisible()) {
      await toggler.click();
    }
    await expect(navigation.locator("#navbarNavDropdown")).toBeVisible();

    await expect(navigation).toHaveScreenshot("iop-group-navigation.png");
  });
});
