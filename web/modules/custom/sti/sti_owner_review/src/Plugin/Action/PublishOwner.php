<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;
use Drupal\sti_owner_review\RecordStatus;

/**
 * Publishes an owner a curator has checked (general review). An owner with a pending AI proposal is decided first.
 */
#[Action(id: 'sti_owner_review_publish', label: new TranslatableMarkup('Publish as reviewed'), type: 'node')]
final class PublishOwner extends OwnerReviewActionBase {

  protected function apply(NodeInterface $owner): ?string {
    if (ProposalApplier::isPending($owner)) {
      return (string) $this->t('has a pending AI proposal; accept or reject it first');
    }
    $owner->set('field_state', 'published');
    $owner->setPublished();
    $this->recordStatus->set($owner, RecordStatus::APPROVED);
    $owner->setNewRevision(TRUE);
    $owner->setRevisionLogMessage('Published from the owner review list.');
    $owner->save();
    return NULL;
  }

}
