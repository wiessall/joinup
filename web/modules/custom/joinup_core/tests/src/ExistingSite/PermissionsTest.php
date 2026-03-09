<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that used permissions are defined.
 *
 * @see https://www.drupal.org/node/3193348
 */
#[Group('joinup_core')]
class PermissionsTest extends JoinupExistingSiteTestBase {

  /**
   * Tests that roles permissions are defined.
   */
  #[DataProvider('providerPermissionTypes')]
  public function testPermissionsAreDefined(string $entity_type_id, string $label): void {
    $output = [];
    $all_permissions = $this->getAllPermissions($entity_type_id);
    $roles = \Drupal::entityTypeManager()->getStorage($entity_type_id)->loadMultiple();
    foreach ($roles as $role) {
      $role_permissions = $role->getPermissions();
      $valid_role_permissions = array_intersect($role_permissions, $all_permissions);
      $invalid_role_permissions = array_diff($role_permissions, $valid_role_permissions);
      if (!empty($invalid_role_permissions)) {
        $output[] = "$label: {$role->id()}";
        $output = [
          ...$output,
          ...array_map(
            function (string $permission): string {
              return "- $permission";
            }, $invalid_role_permissions),
        ];
      }
    }
    $this->assertEmpty($output, "Found undefined permissions (see https://www.drupal.org/node/3193348):\n" . implode("\n", $output));
  }

  /**
   * Provides test cases for ::testPermissionsAreDefined().
   *
   * @return \string[][]
   *   See self::providerPermissionTypes() signature to learn about the array
   *   structure.
   *
   * @see testPermissionsAreDefined
   */
  public static function providerPermissionTypes(): array {
    return [
      [
        'user_role',
        'Role',
      ],
      [
        'og_role',
        'OG role',
      ],
    ];
  }

  /**
   * Provides the list of all permissions.
   *
   * @param string $entity_type_id
   *   Either 'user_role', or 'og_role'.
   *
   * @return array
   *   A list of permission names.
   */
  protected function getAllPermissions(string $entity_type_id): array {
    $permissions = array_keys(\Drupal::service('user.permissions')->getPermissions());

    // For the OG permissions test case add also the OG permissions.
    if ($entity_type_id === 'og_role') {
      /** @var \Drupal\og\PermissionManagerInterface $og_permission_manager */
      $og_permission_manager = \Drupal::service('og.permission_manager');
      /** @var \Drupal\og\GroupTypeManagerInterface $og_group_type_manager */
      $og_group_type_manager = \Drupal::service('og.group_type_manager');
      $collection_content_bundle_ids = $og_group_type_manager->getGroupContentBundleIdsByGroupBundle('node', 'collection');
      $solution_content_bundle_ids = $og_group_type_manager->getGroupContentBundleIdsByGroupBundle('node', 'solution');
      $permissions = array_unique([
        ...$permissions,
        ...array_keys($og_permission_manager->getDefaultPermissions('node', 'collection', $collection_content_bundle_ids + [
          // Solution is not OG group content, but it acts in the same way.
          'node' => ['solution'],
        ])),
        ...array_keys($og_permission_manager->getDefaultPermissions('node', 'solution', $solution_content_bundle_ids)),
      ]);
    }
    return $permissions;
  }

}
