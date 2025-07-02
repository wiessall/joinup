/**
 * @file
 * Solution homepage components.
 */

import { test, expect } from "@playwright/test";
import execDrush from "../utils/functions";

// Test for superadmin (logged-in case)
test.describe("Solution homepage - superadmin", () => {
  test("should render correctly for superadmin", async ({ page }) => {
    const command = `uli --name="admin"`;
    const loginUrl = execDrush(command).toString().trim();

    await page.goto(loginUrl);

    const message = page.locator(
      "[data-drupal-messages] div.messages--status, [data-drupal-messages] div.alert-success",
    );

    await expect(message).toContainText(
      "You have used a one-time login link. You can set your new password now.",
    );

    await page.goto(
      "/collection/portal/solution/information-sharing-protocols",
    );

    await expect(page.locator(".region-header")).toHaveScreenshot(
      "group-header-superadmin.png",
    );
    await expect(page.locator(".content .region-content-top")).toHaveScreenshot(
      "action-buttons-on-top.png",
    );
  });
});

// Test for anonymous (no login)
test.describe("Solution homepage - anonymous", () => {
  test("should render correctly for anonymous", async ({ page }) => {
    await page.goto(
      "/collection/portal/solution/information-sharing-protocols",
    );

    await expect(page.locator(".region-header")).toHaveScreenshot(
      "group-header-anonymous.png",
    );
    await expect(page.locator(".content .region-content-top")).toHaveScreenshot(
      "action-buttons-on-top.png",
    );
  });
});
