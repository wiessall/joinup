/**
 * @file
 * Main config file for Playwright.
 * See https://playwright.dev/docs/test-configuration
 */

import * as path from "path";
import { defineConfig, devices } from "@playwright/test";

const baseDir = "./tests/playwright";
export default defineConfig({
  testDir: path.resolve(baseDir, "tests"),
  outputDir: path.resolve(baseDir, "test-results"),
  // Global setup and teardown.
  globalSetup: require.resolve(path.resolve(baseDir, "global-setup")),
  globalTeardown: require.resolve(path.resolve(baseDir, "global-teardown")),
  // Run tests in files in parallel.
  fullyParallel: true,
  // Fail the build on CI if you accidentally left test.only in the source code.
  forbidOnly: !!process.env.CI,
  // Retry on CI only.
  retries: process.env.GITLAB_CI ? 3 : 0,
  // Opt out of parallel tests on CI.
  workers: process.env.CI ? 1 : undefined,
  // Reporter to use. See https://playwright.dev/docs/test-reporters
  reporter: [
    [
      "html",
      {
        open: "never",
        outputFolder: path.resolve(baseDir, "playwright-report"),
      },
    ],
  ],
  // Shared settings for all the projects below. See https://playwright.dev/docs/api/class-testoptions.
  use: {
    // Base URL to use in actions like `await page.goto('/')`.
    baseURL: process.env.DRUPAL_BASE_URL,
    // Collect trace when retrying the failed test. See https://playwright.dev/docs/trace-viewer
    trace: "on-first-retry",
    // Ignore certificate errors (for ddev).
    ignoreHTTPSErrors: true,
    launchOptions: {
      args: [
        // https://peter.sh/experiments/chromium-command-line-switches/
        // Disable any hardware-related influence.
        "--disable-gpu",
        "--disable-gpu-rasterization",
        "--disable-gpu-compositing",
        "--disable-font-subpixel-positioning",
        "--disable-software-rasterizer",
        "--ppapi-subpixel-rendering-setting=0",
        "--force-device-scale-factor=1",
        "--force-color-profile=srgb",
        "--font-render-hinting=none",
        "--disable-skia-runtime-opts",
        "--disable-lcd-text",
        // Hide scrollbars for consistency between headed and headless.
        "--hide-scrollbars",
      ],
    },
  },

  // Test under chrome desktop and mobile.
  projects: [
    {
      name: "desktop-chrome",
      use: { ...devices["Desktop Chrome"] },
    },
    {
      name: "mobile-chrome",
      use: { ...devices["Pixel 5"] },
    },
  ],
});
