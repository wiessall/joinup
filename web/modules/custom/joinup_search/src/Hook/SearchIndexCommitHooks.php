<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\joinup_group\Entity\SharingInterface;
use Drupal\joinup_search\SearchIndexCommitter;
use Drupal\node\NodeInterface;

/**
 *
 */
readonly class SearchIndexCommitHooks {

  public function __construct(protected SearchIndexCommitter $committer) {}

  #[Hook('node_insert')]
  #[Hook('node_update')]
  public function commit(NodeInterface $node): void {
    if ($node instanceof SharingInterface) {
      $this->committer->commitPendingChanges();
    }
  }

}
