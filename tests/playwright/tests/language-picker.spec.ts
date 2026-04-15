/**
 * @file
 * Language picker.
 */

import { test, expect } from "@playwright/test";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";
import { projectConfig } from "../config/project.config";

test.describe("Language picker", () => {
  test("Testing the language picker", async ({ page }) => {
    await authUtils.login(projectConfig.users.translator, page);
    await page.goto("/");
    await pageUtils.prepareForScreenshot(page);

    // Initial closed state.
    await expect(page.locator(".language-picker")).toHaveScreenshot(
      "block-language-picker-closed.png",
    );

    const button = page.locator(".language-picker .language-picker__button");
    const dropdown = page.locator(
      ".language-picker .language-picker__dropdown-menu",
    );

    await button.click();
    await expect(button).toHaveAttribute("aria-expanded", "true");
    await expect(dropdown).toHaveClass(/show/);
    await expect(dropdown).toBeVisible();
    await expect(dropdown).toHaveScreenshot("block-language-picker-open.png");
    await button.click();
    await expect(button).toHaveAttribute("aria-expanded", "false");
    await expect(dropdown).not.toHaveClass(/show/);
    await expect(dropdown).toBeHidden();
  });
});
