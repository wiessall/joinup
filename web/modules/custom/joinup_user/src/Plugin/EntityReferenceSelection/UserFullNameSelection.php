<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\Entity\Query\QueryInterface;

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
   */
  protected function buildEntityQuery($match = NULL, $match_operator = 'CONTAINS'): QueryInterface {
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

    // Adding the permission check is sadly insufficient for users: core
    // requires us to also know about the concept of 'blocked' and 'active'.
    if (!$this->currentUser->hasPermission('administer users')) {
      $query->condition('status', 1);
    }

    $configuration = $this->getConfiguration();
    if (!empty($configuration['match_limit'])) {
      $query->range(0, $configuration['match_limit']);
    }

    return $query;
  }

}
