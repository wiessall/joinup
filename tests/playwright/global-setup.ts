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

  // The Interoperable Europe theme rule is pinned to the group id the live site
  // uses, which does not exist on a test install, so the IOP theme would never
  // be applied. Point it at the Interoperable Europe Academy group instead.
  const academyUuid = "1cc1b2c4-f65a-49cb-9a31-d5a49f283ea3";
  const groupId = await shellUtils.execDrush(
    `php:eval "print \\Drupal::service('entity.repository')->loadEntityByUuid('node', '${academyUuid}')?->id();"`,
  );
  if (groupId) {
    await shellUtils.execDrush(
      `config:set theme_rule.rule.interoperable_europe conditions.group_context.limit_to_groups.0 ${groupId}`,
    );
  }
}
