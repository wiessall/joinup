/**
 * @file Utility functions for Playwright.
 */

import { execSync } from "child_process";
import { Page } from "@playwright/test";

/**
 * Run drush command locally or remotely depending on the environment.
 * Generally you'll use this function and let it figure out
 * how to execute Drush (locally, remotely, native OS, inside container, etc.).
 *
 * @param {string} cmd - The Drush command.
 * @param {string[]} [args=[]] - Array of string arguments to pass to Drush.
 * @param {string[]} [options=[]] - Array of string options to pass to Drush.
 * @return {string} The output from executing the command in a shell.
 */
export function execDrush(
  cmd: string,
  args: string[] = [],
  options: string[] = [],
): string {
  let output = "";

  if (!Array.isArray(args)) {
    throw new Error("execDrush: args must be an array.");
  }

  if (!Array.isArray(options)) {
    throw new Error("execDrush: options must be an array.");
  }

  const drushAlias = "./vendor/bin/drush";
  const argsString = args.join(" ");
  const optionsString = options.join(" ");
  const command = `${drushAlias} ${cmd} ${argsString} ${optionsString}`;

  try {
    output = execSync(command, { encoding: "utf-8" }).toString();
  } catch (error) {
    throw new Error(`execDrush error: ${(error as Error).message}`);
  }

  return output;
}

/**
 * Wait until Webtools finishes processing.
 *
 * @param {Page} page Playwright page instance.
 * @return {Promise<void>} Resolves when Webtools is idle or missing.
 */
export async function waitForWebtools(page: Page): Promise<void> {
  await page.waitForFunction(
    // @ts-expect-error $wt is injected by Webtools.
    () => typeof window.$wt === "undefined" || window.$wt.inProgress === false,
    30000,
  );
}
