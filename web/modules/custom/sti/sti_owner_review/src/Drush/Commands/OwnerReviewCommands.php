<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_owner_review\Completeness;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Puts incomplete owners in the review queue.
 */
final class OwnerReviewCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly Completeness $completeness,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Moves every incomplete owner to needs_update, keeping it published.
   *
   * Incomplete: no webpage, no description, or a description under Completeness::MIN_DESCRIPTION characters.
   */
  #[CLI\Command(name: 'sti-owner:request-update')]
  #[CLI\Option(name: 'dry-run', description: 'Only list the owners and what they lack.')]
  #[CLI\Usage(name: 'drush sti-owner:request-update --dry-run', description: 'See which owners would be queued.')]
  public function requestUpdate(array $options = ['dry-run' => FALSE]): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $done = $failed = 0;
    foreach (array_chunk($this->completeness->incompleteIds(), 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $owner) {
        $this->output()->writeln(sprintf('%d %s: %s', $owner->id(), $owner->label(), implode(', ', Completeness::gaps($owner))));
        if ($options['dry-run']) {
          continue;
        }
        try {
          if ($this->completeness->requestUpdate($owner) === NULL) {
            $done++;
          }
        }
        catch (\RuntimeException $e) {
          $failed++;
          $this->logger()->error($e->getMessage());
        }
      }
      $storage->resetCache($chunk);
    }
    if ($options['dry-run']) {
      $this->logger()->notice('Dry run: nothing changed.');
      return;
    }
    $this->logger()->success(sprintf('%d owners moved to needs_update, %d failed.', $done, $failed));
  }

}
