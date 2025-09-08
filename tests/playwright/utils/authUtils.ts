/**
 * @file Authentication utilities.
 */

import { Page } from "@playwright/test";
import { projectConfig } from "../config/project.config";

/**
 * Check if the current session is logged in.
 *
 * @param {Page} page - Playwright page instance.
 * @return {Promise<boolean>} - True if logged in, false otherwise.
 */
async function isLoggedIn(page: Page): Promise<boolean> {
  const body = page.locator("body");
  const classAttr = await body.getAttribute("class");
  return classAttr?.includes("user-logged-in") ?? false;
}

/**
 * Log out the current user by clearing cookies if logged in.
 *
 * @param {Page} page - Playwright page instance.
 */
async function logout(page: Page): Promise<void> {
  if (await isLoggedIn(page)) {
    await page.context().clearCookies();
  }
}

/**
 * Log in with the given credentials.
 *
 * Note: This uses the login form instead of drush uli on purpose; to avoid
 * concurrency issues when multiple tests log in as the same user.
 *
 * @param {{ email: string, password: string }} user - The user credentials (see `drupalConfig.users`).
 * @param {Page} page - Playwright page instance.
 */
async function login(
  user: { email: string; password: string },
  page: Page,
): Promise<void> {
  await logout(page);
  await page.goto(projectConfig.logInUrl);
  await page.locator("[id='edit-email']").fill(user.email);
  await page.locator("[id='edit-password']").fill(user.password);
  await page.locator("[id='edit-submit']").click();
}

export default {
  login,
  logout,
};
