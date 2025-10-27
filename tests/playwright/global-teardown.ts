/**
 * @file
 * Global teardown.
 */

import shellUtils from "./utils/shellUtils";

export default async function globalTeardown() {
  // Disable test mode (see \Joinup\TestModeManager::clear).
  await shellUtils.execDrush(
    "config:set joinup_test.settings test_mode 'NULL'",
  );
}
