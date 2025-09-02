/**
 * @file
 * Language picker.
 */

import { test, expect } from "@playwright/test";
import { userLogin } from "../utils/functions";

test.describe("Language picker", () => {
  test("Testing the language picker", async ({ page }) => {
    await userLogin(page, "translator");
    await page.goto("/");

    // Initial closed state.
    await expect(page.locator(".block-language-picker")).toHaveScreenshot(
      `block-language-picker-closed.png`,
    );

    const button = page.locator(
      ".block-language-picker #language-picker__button",
    );
    const dropdown = page.locator(".block-language-picker .dropdown-menu");

    await button.focus();
    await page.keyboard.press("Enter");

    await expect(button).toHaveAttribute("aria-expanded", "true");
    await expect(dropdown).toHaveClass(/show/);
    await expect(dropdown).toBeVisible();
    await expect
      .soft(dropdown)
      .toHaveScreenshot(`block-language-picker-open.png`);

    await page.keyboard.press("Escape");

    await expect(button).toHaveAttribute("aria-expanded", "false");
    await expect(dropdown).not.toHaveClass(/show/);
    await expect(dropdown).toBeHidden();
  });
});
