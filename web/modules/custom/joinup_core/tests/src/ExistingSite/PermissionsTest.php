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
   * Tests that roles permissions are defined.
   *
   * @dataProvider providerPermissionTypes
   */
  public function testPermissionsAreDefined(string $entity_type_id, string $label): void {
    $output = [];
    $all_permissions = $this->getAllPermissions($entity_type_id);
    $roles = \Drupal::entityTypeManager()->getStorage($entity_type_id)->loadMultiple();
    foreach ($roles as $role) {
      $role_permissions = $role->getPermissions();
      // @todo Temporary we're allowing some exceptions. These exceptions will
      //   be removed in ISAICP-7313.
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7313
      $role_permissions = array_diff($role_permissions, $this->getPermissionExceptions());
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
  public function providerPermissionTypes(): array {
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
      $collection_content_bundle_ids = $og_group_type_manager->getGroupContentBundleIdsByGroupBundle('rdf_entity', 'collection');
      $solution_content_bundle_ids = $og_group_type_manager->getGroupContentBundleIdsByGroupBundle('rdf_entity', 'solution');
      $permissions = array_unique([
        ...$permissions,
        ...array_keys($og_permission_manager->getDefaultPermissions('rdf_entity', 'collection', $collection_content_bundle_ids + [
          // Solution is not OG group content, but it acts in the same way.
          'rdf_entity' => ['solution'],
        ])),
        ...array_keys($og_permission_manager->getDefaultPermissions('rdf_entity', 'solution', $solution_content_bundle_ids)),
      ]);
    }
    return $permissions;
  }

  /**
   * Returns a list of permissions to be temporarily excepted from the test.
   *
   * Temporary we're allowing some exceptions. These exceptions will be removed
   * in ISAICP-7313.
   *
   * @return string[]
   *   A list of permissions to be temporarily excepted from the test.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7313
   */
  protected function getPermissionExceptions(): array {
    return [
      'transition any node discussion from deletion_request to validated using the community_content - node:post_moderated workflow',
      'transition any node discussion from draft to draft using the community_content - node:post_moderated workflow',
      'transition any node discussion from draft to draft using the community_content - node:pre_moderated workflow',
      'transition any node discussion from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition any node discussion from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition any node discussion from validated to validated using the community_content - node:post_moderated workflow',
      'transition any node discussion from validated to validated using the community_content - node:pre_moderated workflow',
      'transition any node document from deletion_request to validated using the community_content - node:post_moderated workflow',
      'transition any node document from draft to draft using the community_content - node:post_moderated workflow',
      'transition any node document from draft to draft using the community_content - node:pre_moderated workflow',
      'transition any node document from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition any node document from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition any node document from validated to validated using the community_content - node:post_moderated workflow',
      'transition any node document from validated to validated using the community_content - node:pre_moderated workflow',
      'transition any node event from deletion_request to validated using the community_content - node:post_moderated workflow',
      'transition any node event from draft to draft using the community_content - node:post_moderated workflow',
      'transition any node event from draft to draft using the community_content - node:pre_moderated workflow',
      'transition any node event from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition any node event from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition any node event from validated to validated using the community_content - node:post_moderated workflow',
      'transition any node event from validated to validated using the community_content - node:pre_moderated workflow',
      'transition any node news from deletion_request to validated using the community_content - node:post_moderated workflow',
      'transition any node news from draft to draft using the community_content - node:post_moderated workflow',
      'transition any node news from draft to draft using the community_content - node:pre_moderated workflow',
      'transition any node news from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition any node news from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition any node news from validated to validated using the community_content - node:post_moderated workflow',
      'transition any node news from validated to validated using the community_content - node:pre_moderated workflow',
      'transition group from __new__ to draft using the collection - collection_workflow workflow',
      'transition group from __new__ to draft using the solution - solution_workflow workflow',
      'transition group from __new__ to proposed using the collection - collection_workflow workflow',
      'transition group from __new__ to proposed using the solution - solution_workflow workflow',
      'transition group from __new__ to validated using the collection - collection_workflow workflow',
      'transition group from __new__ to validated using the solution - solution_workflow workflow',
      'transition group from archival_request to archived using the collection - collection_workflow workflow',
      'transition group from archival_request to validated using the collection - collection_workflow workflow',
      'transition group from blacklisted to draft using the solution - solution_workflow workflow',
      'transition group from blacklisted to proposed using the solution - solution_workflow workflow',
      'transition group from blacklisted to validated using the solution - solution_workflow workflow',
      'transition group from draft to draft using the collection - collection_workflow workflow',
      'transition group from draft to draft using the solution - solution_workflow workflow',
      'transition group from draft to proposed using the collection - collection_workflow workflow',
      'transition group from draft to proposed using the solution - solution_workflow workflow',
      'transition group from draft to validated using the collection - collection_workflow workflow',
      'transition group from draft to validated using the solution - solution_workflow workflow',
      'transition group from needs_update to draft using the solution - solution_workflow workflow',
      'transition group from needs_update to proposed using the solution - solution_workflow workflow',
      'transition group from needs_update to validated using the solution - solution_workflow workflow',
      'transition group from proposed to draft using the collection - collection_workflow workflow',
      'transition group from proposed to draft using the solution - solution_workflow workflow',
      'transition group from proposed to needs_update using the solution - solution_workflow workflow',
      'transition group from proposed to proposed using the collection - collection_workflow workflow',
      'transition group from proposed to proposed using the solution - solution_workflow workflow',
      'transition group from proposed to validated using the collection - collection_workflow workflow',
      'transition group from proposed to validated using the solution - solution_workflow workflow',
      'transition group from validated to blacklisted using the solution - solution_workflow workflow',
      'transition group from validated to draft using the collection - collection_workflow workflow',
      'transition group from validated to draft using the solution - solution_workflow workflow',
      'transition group from validated to needs_update using the solution - solution_workflow workflow',
      'transition group from validated to proposed using the collection - collection_workflow workflow',
      'transition group from validated to proposed using the solution - solution_workflow workflow',
      'transition group from validated to validated using the collection - collection_workflow workflow',
      'transition group from validated to validated using the solution - solution_workflow workflow',
      'transition own node discussion from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node discussion from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node discussion from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node discussion from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node discussion from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node discussion from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node discussion from validated to validated using the community_content - node:pre_moderated workflow',
      'transition own node document from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node document from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node document from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node document from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node document from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node document from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node document from validated to validated using the community_content - node:pre_moderated workflow',
      'transition own node event from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node event from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node event from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node event from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node event from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node event from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node event from validated to validated using the community_content - node:pre_moderated workflow',
      'transition own node news from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node news from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node news from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node news from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node news from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node news from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node news from validated to validated using the community_content - node:pre_moderated workflow',
    ];
  }

}
