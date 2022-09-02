<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;

/**
 * Tests that used permissions are defined.
 *
 * @see https://www.drupal.org/node/3193348
 *
 * @group joinup_core
 */
class PermissionsTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that used permissions are defined.
   */
  public function testPermissionsAreDefined(): void {
    $output = [];
    $all_perms = array_keys(\Drupal::service('user.permissions')->getPermissions());
    /** @var \Drupal\user\RoleInterface $role */
    foreach (\Drupal::entityTypeManager()->getStorage('user_role')->loadMultiple() as $role) {
      $role_perms = $role->getPermissions();
      $valid_role_perms = array_intersect($role_perms, $all_perms);
      $invalid_role_perms = array_diff($role_perms, $valid_role_perms);
      if (!empty($invalid_role_perms)) {
        $output[] = "Role: {$role->id()}";
        $output = [
          ...$output,
          ...array_map(
            function (string $permission): string {
              return "- $permission";
            }, $invalid_role_perms),
        ];
      }
    }
    $this->assertEmpty($output, "Found undefined permissions (see https://www.drupal.org/node/3193348):\n" . implode("\n", $output));
  }

}
