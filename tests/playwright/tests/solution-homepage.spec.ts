/**
 * @file
 * Solution homepage components.
 */

import { test, expect, Page } from "@playwright/test";
import { userLogin, waitForWebtools } from "../utils/functions";

test.describe("Solution homepage components", () => {
  async function validateSolutionPage(page: Page, user: string) {
    await page.goto(
      "/collection/portal/solution/information-sharing-protocols",
    );
    await waitForWebtools(page);

    await expect(page.locator(".region-header")).toHaveScreenshot(
      `group-header-${user}.png`,
    );

    await expect(page.locator(".content .region-content-top")).toHaveScreenshot(
      `action-buttons-on-top.png`,
    );
  }

  test("Anonymous", async ({ page }) => {
    await validateSolutionPage(page, "anonymous");
  });

  test("Administrator", async ({ page }) => {
    await userLogin(page, "admin");
    await validateSolutionPage(page, "superadmin");
  });
});
