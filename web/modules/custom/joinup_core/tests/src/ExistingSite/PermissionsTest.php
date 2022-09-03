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
      // @todo Temporary we're allowing some exceptions. These exceptions will
      //   be removed in ISAICP-7313.
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7313
      $role_perms = array_diff($role_perms, $this->getPermissionExceptions());
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
      'transition own node discussion from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node discussion from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node discussion from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node discussion from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node discussion from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node discussion from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node document from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node document from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node document from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node document from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node document from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node document from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node event from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node event from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node event from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node event from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node event from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node event from validated to validated using the community_content - node:post_moderated workflow',
      'transition own node news from draft to draft using the community_content - node:post_moderated workflow',
      'transition own node news from draft to draft using the community_content - node:pre_moderated workflow',
      'transition own node news from proposed to proposed using the community_content - node:post_moderated workflow',
      'transition own node news from proposed to proposed using the community_content - node:pre_moderated workflow',
      'transition own node news from validated to deletion_request using the community_content - node:post_moderated workflow',
      'transition own node news from validated to validated using the community_content - node:post_moderated workflow',
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
    ];
  }

}
