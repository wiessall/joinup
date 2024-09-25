<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Provide User selection handler that uses full name.
 *
 * Currently, it is only used for private message logic.
 *
 * @EntityReferenceSelection(
 *   id = "joinup_user_full_name",
 *   label = @Translation("Full name selection"),
 *   group = "joinup_user_full_name",
 *   entity_types = {"user"},
 * )
 *
 * @see \Drupal\user\Plugin\EntityReferenceSelection\UserSelection
 */
class UserFullNameSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   *   Thrown when the user doesn't have the needed permissions.
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
    if (
      !(
        $this->currentUser->hasPermission('access user profiles') &&
        $this->currentUser->hasPermission('use private messaging system'))
    ) {
      throw new \Exception('Permissions missing.');
    }

    $query = parent::buildEntityQuery($match, $match_operator);

    // Don't show the user himself.
    $query->condition('uid', $this->currentUser->id(), '<>');

    // Search for matches.
    if (isset($match)) {
      $match_chunks = explode(' ', $match);
      foreach ($match_chunks as $match_chunk) {
        $orGroup = $query->orConditionGroup()
          ->condition('field_user_first_name', $match_chunk, $match_operator)
          ->condition('field_user_family_name', $match_chunk, $match_operator);
        $query->condition($orGroup);
      }
    }

    // Only show users with permissions to use PM
    // (unless any authenticated can use PM).
    if (!in_array('authenticated', $roles = $this->getCanUseRids())) {
      $query->condition('roles.target_id', $roles, 'IN');
    }

    // Adding the permission check is sadly insufficient for users: core
    // requires us to also know about the concept of 'blocked' and 'active'.
    if (!$this->currentUser->hasPermission('administer users')) {
      $query->condition('status', 1);
    }

    // Set the limit according to configuration.
    $configuration = $this->getConfiguration();
    if (!empty($configuration['match_limit'])) {
      $query->range(0, $configuration['match_limit']);
    }

    return $query;
  }

  /**
   * Returns role ids with permission to use PM system.
   *
   * @return string[]
   *   Array of role IDs.
   *
   * @see \Drupal\private_message\Mapper\PrivateMessageMapper::getCanUseRids()
   */
  protected function getCanUseRids(): array {
    $use_pm_permission = 'use private messaging system';
    $roles = array_filter(Role::loadMultiple(), fn(RoleInterface $role) => $role->hasPermission($use_pm_permission));
    return array_keys($roles);
  }

}
