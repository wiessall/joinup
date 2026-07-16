/**
 * @file
 * Mobile navigation drawer.
 *
 * The hamburger drawer is only rendered on small screens (the toggle is
 * d-xl-none), so these tests run on the mobile project only.
 */

import { test, expect, Page, Locator } from "@playwright/test";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";
import { projectConfig } from "../config/project.config";

/**
 * Opens the mobile drawer and returns its locator.
 *
 * @param {Page} page - Playwright page instance.
 * @return {Promise<Locator>} - The opened drawer.
 */
async function openDrawer(page: Page): Promise<Locator> {
  await pageUtils.prepareForScreenshot(page);
  const toggle = page.locator(".mmenu-nav__toggle");
  await expect(toggle).toBeVisible();
  await toggle.click();
  const drawer = page.locator('[aria-label="Mobile navigation"]');
  await expect(drawer).toBeVisible();
  return drawer;
}

test.describe("Mobile navigation", () => {
  test.skip(
    ({ viewport }) => !viewport || viewport.width >= 1200,
    "The mobile drawer is only shown below the xl breakpoint.",
  );

  test("Anonymous mobile drawer", async ({ page }) => {
    await page.goto("/");
    const drawer = await openDrawer(page);
    await expect(drawer).toHaveScreenshot("mobile-navigation-anonymous.png");
  });

  test("Authenticated mobile drawer", async ({ page }) => {
    await authUtils.login(projectConfig.users.user, page);
    await page.goto("/");
    const drawer = await openDrawer(page);
    await expect(drawer).toHaveScreenshot("mobile-navigation-authenticated.png");
  });
});
