<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;

/**
 * Puts incomplete owners in the review queue (needs_update), keeping them published. Complete ones are skipped.
 */
#[Action(id: 'sti_owner_review_request_update', label: new TranslatableMarkup('Request update if incomplete'), type: 'node')]
final class RequestUpdate extends OwnerReviewActionBase {

  protected function apply(NodeInterface $owner): ?string {
    return $this->completeness->requestUpdate($owner);
  }

}
