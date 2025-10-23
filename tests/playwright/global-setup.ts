/**
 * @file
 * Global setup.
 */

import shellUtils from "./utils/shellUtils";

export default async function globalSetup() {
  // Enable test mode (see \Joinup\TestModeManager::set).
  await shellUtils.execDrush(
    "config:set joinup_test.settings test_mode playwright",
  );
}
