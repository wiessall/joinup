/**
 * @file
 * Visual regression tests for tile components and paragraph layouts.
 *
 * Vertical tiles (view_mode_tile) are covered by collection-homepage.spec.ts.
 * This file tests the remaining distinct components:
 *
 * - Configurable listing paragraph (Search API driven tile grid)
 * - Paragraph tiles (curated tile grid)
 * - Accordion paragraph
 * - EIF perspective page
 * - Horizontal tile (view_mode_tile_horizontal)
 */

import { test, expect } from "@playwright/test";
import { projectConfig } from "../config/project.config";
import authUtils from "../utils/authUtils";
import pageUtils from "../utils/pageUtils";
import shellUtils from "../utils/shellUtils";

// Index content so it appears in search and search_api_field listings.
test.beforeAll(async () => {
  await shellUtils.execDrush("search-api:index");
});

test.describe("Configurable listing paragraph", () => {
  test("Vertical tile grid rendered via Search API", async ({ page }) => {
    await page.goto("/topic/egov");
    await pageUtils.prepareForScreenshot(page);

    await expect(
      page.locator(".paragraph--configurable-listing").first(),
    ).toHaveScreenshot("tile-topic-configurable-listing.png");
  });
});

test.describe("Custom page paragraphs", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(
      "/collection/portal-support/frequently-asked-questions-and-resources",
    );
    await pageUtils.prepareForScreenshot(page);
  });

  test("Accordion paragraph", async ({ page }) => {
    const accordion = page.locator(".accordion").first();
    await expect(accordion).toBeVisible();

    // Screenshot closed state.
    await expect(accordion).toHaveScreenshot("paragraph-accordion-closed.png");

    // Open first accordion item and screenshot.
    const firstButton = accordion.locator("button.accordion-button").first();
    await firstButton.click();
    await expect(firstButton).toHaveAttribute("aria-expanded", "true");
    await expect(accordion).toHaveScreenshot("paragraph-accordion-open.png");
  });

  test("Paragraph tiles grid", async ({ page }) => {
    const tilesSection = page.locator(".paragraph--tiles").first();
    await expect(tilesSection).toBeVisible();
    await expect(tilesSection).toHaveScreenshot("paragraph-tiles-grid.png");
  });
});

test.describe("EIF perspective", () => {
  test("EIF perspective tile layout", async ({ page }) => {
    await authUtils.login(projectConfig.users.administrator, page);

    const nid = await shellUtils.execDrush(
      "eval \"echo array_key_first(\\Drupal::entityTypeManager()->getStorage('node')->loadByProperties(['uuid' => 'fc99c508-84dc-4646-a112-5b37bff6f2a7']));\"",
    );

    await page.goto(`/node/${nid}/eif-perspective`);
    await pageUtils.prepareForScreenshot(page);

    await expect(page.locator("main")).toHaveScreenshot(
      "eif-perspective-page.png",
    );
  });
});

test.describe("Horizontal tile", () => {
  test("Discussion horizontal tile layout", async ({ page }) => {
    await page.goto("/discussions");
    await pageUtils.prepareForScreenshot(page);

    const list = page.locator("main").first();
    await expect(list).toBeVisible();
    await expect(list).toHaveScreenshot("discussions-tile-list.png");
  });
});
