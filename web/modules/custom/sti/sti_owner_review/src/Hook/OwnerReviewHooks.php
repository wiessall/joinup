<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;
use Drupal\sti_owner_review\QualityChecks;
use Drupal\sti_owner_review\RecordStatus;

/**
 * Guards the parent field against cycles, keeps the proposal field to reviewers and the enrichment account,
 * keeps quality checks and record status in step with what happens to an owner, and links the review tab.
 */
final class OwnerReviewHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly QualityChecks $qualityChecks,
    private readonly RecordStatus $recordStatus,
  ) {}

  #[Hook('node_presave')]
  public function nodePresave(NodeInterface $node): void {
    if ($node->bundle() !== 'owner') {
      return;
    }
    $this->recordStatus->onProposal($node);
    if ($this->qualityChecks->markEdited($node)) {
      $this->recordStatus->set($node, RecordStatus::CURATED);
    }
  }

  /**
   * "Review AI proposal" in the operations of owners with a pending proposal, e.g. in the owner review list.
   */
  #[Hook('entity_operation')]
  public function entityOperation(EntityInterface $entity): array {
    if (!$entity instanceof NodeInterface || $entity->bundle() !== 'owner' || !ProposalApplier::isPending($entity)) {
      return [];
    }
    $url = Url::fromRoute('sti_owner_review.ai_review', ['node' => $entity->id()]);
    return $url->access() ? ['ai_review' => ['title' => $this->t('Review AI proposal'), 'url' => $url, 'weight' => -10]] : [];
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
