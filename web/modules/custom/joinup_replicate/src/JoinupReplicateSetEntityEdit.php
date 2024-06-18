<?php

declare(strict_types=1);

namespace Drupal\joinup_replicate;

use Drupal\replicate\Events\AfterSaveEvent;
use Drupal\replicate_actions\EventSubscriber\ReplicateSetEntityEdit;

/**
 * Disables early redirect of ReplicateSetEntityEdit.
 *
 * @see \Drupal\replicate_actions\EventSubscriber\ReplicateSetEntityEdit::makeRedirect()
 */
class JoinupReplicateSetEntityEdit extends ReplicateSetEntityEdit {

  /**
   * Does nothing.
   *
   * @param \Drupal\replicate\Events\AfterSaveEvent $event
   *   The event fired by the replicator.
   */
  public function makeRedirect(AfterSaveEvent $event): void {
  }

}
