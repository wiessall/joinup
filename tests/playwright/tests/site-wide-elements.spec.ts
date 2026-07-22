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

  test("Navbar landing page dropdown", async ({ page, viewport }) => {
    // The navbar dropdowns are only shown from the xl breakpoint.
    test.skip(!viewport || viewport.width < 1200);

    // The "About" landing page and its "About Joinup" child come from the
    // joinup_test_content default content installed by `ddev playwright setup`.
    await page.goto("/");
    await pageUtils.prepareForScreenshot(page);

    const navbar = page.locator("header.joinup-navbar");
    const dropdown = page.locator(".dropdown-menu--sub");
    const child = dropdown.getByRole("link", {
      name: "About Joinup",
      exact: true,
    });
    await expect(child).toBeHidden();

    await page.getByRole("button", { name: "About", exact: true }).hover();
    await expect(child).toBeVisible();

    // The dropdown is absolutely positioned below the navbar, so clip from the
    // navbar top to the dropdown bottom to capture both.
    const navBox = (await navbar.boundingBox())!;
    const dropdownBox = (await dropdown.boundingBox())!;
    await expect(page).toHaveScreenshot("homepage-navbar-dropdown.png", {
      clip: {
        x: navBox.x,
        y: navBox.y,
        width: navBox.width,
        height: dropdownBox.y + dropdownBox.height - navBox.y,
      },
    });
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
