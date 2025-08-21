/**
 * @file
 * Solution homepage components.
 */

import { test, expect } from "@playwright/test";
import { execDrush, waitForWebtools } from "../utils/functions";

// Define your test cases
const testCases = [
  { label: "superadmin", nameToLoginWith: "admin" },
  { label: "anonymous", nameToLoginWith: false },
] as const;

testCases.forEach(({ label, nameToLoginWith }) => {
  test.describe(`Solution homepage - ${label}`, () => {
    test(`should render correctly for ${label}`, async ({ page }) => {
      // Log in using Drush ULI if a username is provided
      if (nameToLoginWith) {
        const command = `uli --name="${nameToLoginWith}"`;
        const loginUrl = execDrush(command).toString().trim();

        await page.goto(loginUrl);

        const message = page.locator(
          "[data-drupal-messages] div.messages--status, [data-drupal-messages] div.alert-success",
        );

        await expect(message).toContainText(
          "You have used a one-time login link. You can set your new password now.",
        );
      }

      // Visit the solution page
      await page.goto(
        "/collection/portal/solution/information-sharing-protocols",
      );
      await waitForWebtools(page);

      await expect(page.locator(".region-header")).toHaveScreenshot(
        `group-header-${label}.png`,
      );
      await expect(
        page.locator(".content .region-content-top"),
      ).toHaveScreenshot(`action-buttons-on-top.png`);
    });
  });
});
