<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;

/**
 * Rejects the whole AI proposal; the owner stays in needs_update.
 */
#[Action(id: 'sti_owner_review_reject', label: new TranslatableMarkup('Reject AI proposal'), type: 'node')]
final class RejectProposal extends OwnerReviewActionBase {

  protected function apply(NodeInterface $owner): ?string {
    if (!ProposalApplier::isPending($owner)) {
      return (string) $this->t('no pending AI proposal');
    }
    $this->applier->reject($owner);
    return NULL;
  }

}
