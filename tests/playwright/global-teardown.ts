/**
 * @file
 * Global teardown.
 */

import { execDrush } from "./utils/functions";

export default async function globalTeardown() {
  // Disable test mode (see \Joinup\TestModeManager::clear).
  execDrush("config:set joinup_test.settings test_mode 'NULL' -y");
}
