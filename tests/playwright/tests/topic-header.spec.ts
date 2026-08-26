/**
 * @file
 * Topic header description and its "See more" button.
 *
 * A long description is clamped to a couple of lines and expanded by the
 * button. The regression in ISAICP-11732 hid it entirely while collapsed and
 * kept it clamped after expanding, so the height is asserted in both states on
 * top of the screenshots.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

/**
 * Returns what the description box shows right now.
 */
async function describeBox(box) {
  return box.evaluate((element: HTMLElement) => ({
    visible: element.getClientRects().length > 0,
    clientHeight: element.clientHeight,
    scrollHeight: element.scrollHeight,
    lineClamp: getComputedStyle(element).webkitLineClamp,
  }));
}

test.describe("Topic header", () => {
  test("Long description is clamped, then fully expanded", async ({ page }) => {
    await page.goto("/topic/ptgov");
    await pageUtils.prepareForScreenshot(page);

    const description = page.locator("#collapseSeeMore");
    const button = page.locator(".js-see-more-btn");
    await expect(description).toBeVisible();
    await expect(button).toBeVisible();

    // Collapsed: a couple of lines are readable, the rest is cut off.
    const collapsed = await describeBox(description);
    expect(collapsed.visible).toBe(true);
    expect(collapsed.clientHeight).toBeGreaterThan(0);
    expect(collapsed.clientHeight).toBeLessThan(collapsed.scrollHeight);
    await expect(button).toHaveAttribute("aria-expanded", "false");
    await expect(page.locator(".header-topic")).toHaveScreenshot(
      "topic-header-collapsed.png",
    );

    // Paragraphs are inlined for the clamp, so the blank line between them is
    // drawn by a pseudo element rather than by the paragraph margins.
    await expect(description.locator("p")).toHaveCount(3);

    await button.click();

    // Expanded: the whole description is visible and the clamp is released.
    await expect
      .poll(async () => (await describeBox(description)).lineClamp)
      .toBe("none");
    const expanded = await describeBox(description);
    expect(expanded.clientHeight).toBeGreaterThanOrEqual(expanded.scrollHeight);
    expect(expanded.clientHeight).toBeGreaterThan(collapsed.clientHeight);
    await expect(button).toHaveAttribute("aria-expanded", "true");
    await expect(page.locator(".header-topic")).toHaveScreenshot(
      "topic-header-expanded.png",
    );

    // Collapsing restores the clamped height.
    await button.click();
    await expect
      .poll(async () => (await describeBox(description)).clientHeight)
      .toBe(collapsed.clientHeight);
    await expect(button).toHaveAttribute("aria-expanded", "false");
  });
});
