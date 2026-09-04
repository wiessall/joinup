/**
 * @file
 * Visual regression tests for the "Show archived" facet toggle.
 */

import { test, expect } from "@playwright/test";
import pageUtils from "../utils/pageUtils";

const toggles = [
  {
    name: "Group content listing",
    path: "/collection/portal-support",
    region: ".collection__collection-settings .facet-region--top",
    facetId: "collection_content_status",
    archivedItem: "Consultation on the 2019 interoperability action plan",
    snapshot: "archived-toggle-group-listing",
  },
  {
    name: "Discussions listing",
    path: "/collection/portal-support/discussions",
    region: ".facet-region--top",
    facetId: "entity_status",
    archivedItem: "Retired guidance on legacy data exchange formats",
    snapshot: "archived-toggle-discussions-listing",
  },
];

test.describe("Show archived toggle", () => {
  toggles.forEach((toggle) => {
    test(`${toggle.name} toggle`, async ({ page }) => {
      await page.goto(toggle.path);
      await pageUtils.prepareForScreenshot(page);

      const region = page.locator(toggle.region).first();
      await expect(region).toBeVisible();

      const facet = region.locator(
        `[data-drupal-facet-id="${toggle.facetId}"]`,
      );
      const item = facet.locator(".facet-item");
      await expect(item).toHaveClass(/form-check/);
      await expect(item).toHaveClass(/form-switch/);

      await expect(facet).toHaveClass(/facet-inactive/);
      await expect(item.getByRole("checkbox")).not.toBeChecked();
      await expect(
        page.getByText(toggle.archivedItem, { exact: false }),
      ).toHaveCount(0);
      await expect(region).toHaveScreenshot(`${toggle.snapshot}.png`);

      await item.getByRole("checkbox").check();
      await expect(facet).toHaveClass(/facet-active/);
      await pageUtils.prepareForScreenshot(page);

      await expect(item.getByRole("checkbox")).toBeChecked();
      await expect(
        page.getByText(toggle.archivedItem, { exact: false }).first(),
      ).toBeVisible();
      await expect(region).toHaveScreenshot(`${toggle.snapshot}-enabled.png`);
    });
  });
});
