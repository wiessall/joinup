/**
 * @file
 * Visual regression tests for non-tile paragraph components.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("Accordion paragraph", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(
      "/collection/portal-support/frequently-asked-questions-and-resources",
    );
    await pageUtils.prepareForScreenshot(page);
  });

  test("Closed and open states", async ({ page }) => {
    const accordion = page.locator(".accordion").first();
    await expect(accordion).toBeVisible();
    await expect(accordion).toHaveScreenshot("paragraph-accordion-closed.png");

    const firstButton = accordion.locator("button.accordion-button").first();
    await firstButton.click();
    await expect(firstButton).toHaveAttribute("aria-expanded", "true");
    await expect(accordion).toHaveScreenshot("paragraph-accordion-open.png");
  });
});
