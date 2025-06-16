/**
 * Useful functions for Playwright.
 */
import { execSync } from "child_process";

/**
 * Run drush command locally or remotely depending on the environment.
 * Generally you'll use this function and let it figure out
 * how to execute Drush (locally, remotely, native OS, inside container, etc.).
 *
 * @param cmd - The Drush command.
 * @param args - Array of string arguments to pass to Drush.
 * @param options - Array of string options to pass to Drush.
 * @returns The output from executing the command in a shell.
 */
export function execDrush(cmd: string, args: string[] = [], options: string[] = []): string {
  let output = "";

  if (!Array.isArray(args)) {
    console.error("execDrush: Pass an array for arguments.");
  }

  if (!Array.isArray(options)) {
    console.error("execDrush: Pass an array for options.");
  }

  const drushAlias = "./vendor/bin/drush";
  const argsString = args.join(" ");
  const optionsString = options.join(" ");
  const command = `${drushAlias} ${cmd} ${argsString} ${optionsString}`;

  try {
    output = execSync(command, { encoding: "utf-8" }).toString();
  } catch (error) {
    console.error(`execDrush error: ${(error as Error).message}`);
  }

  return output;
}
