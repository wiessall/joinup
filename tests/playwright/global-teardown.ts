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

  // Restore the group the Interoperable Europe theme rule points at.
  await shellUtils.execDrush(
    "config:set theme_rule.rule.interoperable_europe conditions.group_context.limit_to_groups.0 723208",
  );
}
