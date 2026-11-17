<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\sti_owner_review\ProposalApplier;

/**
 * Guards the parent field against cycles, and keeps the proposal field to reviewers and the enrichment account.
 */
final class OwnerReviewHooks {

  #[Hook('entity_bundle_field_info_alter')]
  public function bundleFieldInfoAlter(array &$fields, EntityTypeInterface $entity_type, string $bundle): void {
    if ($entity_type->id() === 'node' && isset($fields[ProposalApplier::PARENT_FIELD])) {
      $fields[ProposalApplier::PARENT_FIELD]->addConstraint('StiNoParentCycle');
    }
  }

  #[Hook('entity_field_access')]
  public function fieldAccess(string $operation, FieldDefinitionInterface $field_definition, AccountInterface $account, ?FieldItemListInterface $items = NULL): AccessResultInterface {
    if ($field_definition->getName() !== ProposalApplier::FIELD) {
      return AccessResult::neutral();
    }
    return AccessResult::forbiddenIf(!$account->hasPermission('review owner proposals') && !$account->hasPermission('submit owner proposals'))
      ->cachePerPermissions();
  }

}
