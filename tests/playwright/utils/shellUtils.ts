/**
 * @file Shell utilities.
 */
import { exec as execNode } from "node:child_process";
import { promisify } from "util";
import { projectConfig } from "../config/project.config";

// Promisify exec for async/await usage.
const execPromise = promisify(execNode);

/**
 * Execute a shell command in the Drupal root directory.
 *
 * @param {string} command - The shell command to execute.
 * @return {Promise<string>} The standard output from the command.
 */
async function exec(command: string): Promise<string> {
  try {
    const { stdout } = await execPromise(command);
    return stdout.toString().trim();
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    throw new Error(`Exec command failed: ${command}\n${msg}`);
  }
}

/**
 * Execute a Drush command.
 *
 * @param {string} command - The Drush command to execute.
 * @return {Promise<string>} The standard output from the command.
 */
async function execDrush(command: string): Promise<string> {
  const cmdDrush = `./vendor/bin/drush ${command} --uri=${projectConfig.baseUrl} --no-interaction`;
  try {
    const { stdout } = await execPromise(cmdDrush);
    return stdout.toString().trim();
  } catch (err) {
    const msg = err instanceof Error ? err.message : String(err);
    throw new Error(`Drush command failed: ${cmdDrush}\n${msg}`);
  }
}

export default {
  exec,
  execDrush,
};
