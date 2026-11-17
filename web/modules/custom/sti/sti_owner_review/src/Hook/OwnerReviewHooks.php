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
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;
use Drupal\sti_owner_review\QualityChecks;

/**
 * Guards the parent field against cycles, keeps the proposal field to reviewers and the enrichment account, and
 * marks AI-generated fields a curator changes as edited.
 */
final class OwnerReviewHooks {

  public function __construct(private readonly QualityChecks $qualityChecks) {}

  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() === 'owner') {
      $this->qualityChecks->markEdited($node);
    }
  }

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
