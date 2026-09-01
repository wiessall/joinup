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
    await expect(drawer).toHaveScreenshot(
      "mobile-navigation-authenticated.png",
    );
  });

  test("Second-level navbar items are reachable in the drawer", async ({
    page,
  }) => {
    // The "About" landing page and its "About Joinup" child come from the
    // joinup_test_content default content installed by `ddev playwright setup`.
    await page.goto("/");
    const drawer = await openDrawer(page);

    const parentRow = drawer
      .getByRole("link", { name: "About", exact: true })
      .locator("xpath=ancestor::li[1]");
    await expect(parentRow).toBeVisible();

    const childLink = drawer.getByRole("link", {
      name: "About Joinup",
      exact: true,
    });
    await expect(childLink).not.toBeInViewport();

    // Dispatch the click on the row, not the link, to open the submenu panel.
    await parentRow.dispatchEvent("click");
    await expect(childLink).toBeInViewport();

    await expect(drawer).toHaveScreenshot("mobile-navigation-second-level.png");
  });
});
