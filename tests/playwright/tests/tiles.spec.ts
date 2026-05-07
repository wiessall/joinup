/**
 * @file
 * Visual regression tests for tile components.
 *
 * Vertical tiles (view_mode_tile) are covered by collection-homepage.spec.ts.
 * This file covers the remaining tile variants:
 *
 * - Configurable listing paragraph (Search API driven tile grid)
 * - Paragraph tiles (curated tile grid)
 * - Horizontal tile (view_mode_tile_horizontal)
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

test.describe("Configurable listing paragraph", () => {
  test("Vertical tile grid rendered via Search API", async ({ page }) => {
    await page.goto("/topic/ptgov");
    await pageUtils.prepareForScreenshot(page);

    const listing = page.locator(".paragraph--configurable-listing").first();
    await expect(listing).toBeVisible();
    await expect(listing).toHaveScreenshot(
      "tile-topic-configurable-listing.png",
    );
  });
});

test.describe("Paragraph tiles", () => {
  test("Paragraph tiles grid", async ({ page }) => {
    await page.goto(
      "/collection/portal-support/frequently-asked-questions-and-resources",
    );
    await pageUtils.prepareForScreenshot(page);

    const tilesSection = page.locator(".paragraph--tiles").first();
    await expect(tilesSection).toBeVisible();
    await expect(tilesSection).toHaveScreenshot("paragraph-tiles-grid.png");
  });
});

test.describe("Horizontal tile", () => {
  test("Discussion horizontal tile layout", async ({ page }) => {
    await page.goto("/discussions");
    await pageUtils.prepareForScreenshot(page);

    const list = page.locator(".view-id-discussions_sitewide").first();
    await expect(list).toBeVisible();
    await expect(list).toHaveScreenshot("discussions-tile-list.png");
  });
});
