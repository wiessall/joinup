/**
 * @file
 * Visual regression tests for the EIF perspective page.
 */

import { test, expect } from "@playwright/test";
import { projectConfig } from "../config/project.config";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";

test.describe("EIF perspective", () => {
  test("EIF perspective tile layout", async ({ page }) => {
    await authUtils.login(projectConfig.users.administrator, page);

    await page.goto(
      "/collection/portal-support/solution/information-sharing-protocols/eif-perspective",
    );
    await pageUtils.prepareForScreenshot(page);

    await expect(page.locator("main")).toHaveScreenshot(
      "eif-perspective-page.png",
    );
  });
});
