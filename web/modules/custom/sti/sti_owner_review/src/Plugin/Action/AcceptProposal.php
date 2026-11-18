<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\sti_owner_review\ProposalApplier;

/**
 * Accepts the whole AI proposal. A proposal that needs a curator's judgement is left for the review tab.
 */
#[Action(id: 'sti_owner_review_accept', label: new TranslatableMarkup('Accept AI proposal'), type: 'node')]
final class AcceptProposal extends OwnerReviewActionBase {

  protected function apply(NodeInterface $owner): ?string {
    $proposal = ProposalApplier::proposal($owner);
    if (!ProposalApplier::isPending($owner)) {
      return (string) $this->t('no pending AI proposal');
    }
    if (isset($proposal['fields']['parent']) || ($proposal['source_type'] ?? '') === 'search_grounded') {
      return (string) $this->t('proposes a parent or a description from search alone; review it in its tab');
    }
    $this->applier->accept($owner, array_keys($proposal['fields']));
    return NULL;
  }

}
