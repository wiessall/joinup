/**
 * @file
 * Global setup.
 */

import { execDrush } from "./utils/functions";

export default async function globalSetup() {
  // Enable test mode (see \Joinup\TestModeManager::set).
  execDrush("config:set joinup_test.settings test_mode playwright -y");
  execDrush("pm:enable joinup_test_content");
}
