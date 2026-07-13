/**
 * @file
 * Site-wide elements visual regression tests.
 */

import { test, expect } from "@playwright/test";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";
import { projectConfig } from "../config/project.config";

test.describe("Site-wide elements", () => {
  test("Homepage site-wide elements", async ({ page }) => {
    // Test elements as anonymous.
    await page.goto("/");
    await pageUtils.prepareForScreenshot(page);

    await expect(page.locator("header.joinup-navbar")).toHaveScreenshot(
      "homepage-joinup-navbar-anonymous.png",
    );

    // Test elements as developer role.
    await authUtils.login(projectConfig.users.developer, page);

    await page.goto("/");
    await pageUtils.prepareForScreenshot(page);

    await expect(page.locator(".bcl-header")).toHaveScreenshot(
      "homepage-bcl-header.png",
    );

    await expect(page.locator("header.joinup-navbar")).toHaveScreenshot(
      "homepage-joinup-navbar.png",
    );

    await page.locator(".page__footer .joinup-version").evaluate((element) => {
      element.textContent = "0.0.0";
    });

    await expect(page.locator(".page__footer")).toHaveScreenshot(
      "homepage-footer.png",
    );
  });

  test("BCL header on a custom page", async ({ page }) => {
    await authUtils.login(projectConfig.users.developer, page);

    await page.goto(
      "/collection/portal-support/about-interoperability-solutions-platform",
    );
    await pageUtils.prepareForScreenshot(page);

    await expect(page.locator(".bcl-header")).toHaveScreenshot(
      "interoperability-solutions-platform-bcl-header.png",
    );
  });
});
