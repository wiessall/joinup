<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;

/**
 * Moves junk owners to deletion_request; a pending AI proposal is rejected with it.
 */
#[Action(id: 'sti_owner_review_request_deletion', label: new TranslatableMarkup('Request deletion'), type: 'node')]
final class RequestDeletion extends OwnerReviewActionBase {

  protected function apply(NodeInterface $owner): ?string {
    if (ProposalApplier::isPending($owner)) {
      $this->applier->reject($owner, TRUE);
      return NULL;
    }
    $owner->set('field_state', 'deletion_request');
    $owner->setNewRevision(TRUE);
    $owner->setRevisionLogMessage('Deletion requested from the owner review list.');
    $owner->save();
    return NULL;
  }

}
