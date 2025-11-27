/**
 * @file
 * Solution homepage components.
 */

import { test, expect, Page } from "@playwright/test";
import { projectConfig } from "../config/project.config";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";

test.describe("Solution homepage components", () => {
  async function validateSolutionPage(page: Page, user: string) {
    await page.goto(
      "/collection/portal-support/solution/information-sharing-protocols",
    );
    await pageUtils.prepareForScreenshot(page);

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
    await authUtils.login(projectConfig.users.administrator, page);
    await validateSolutionPage(page, "administrator");
  });
});
