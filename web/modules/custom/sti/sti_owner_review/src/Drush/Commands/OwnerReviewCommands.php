<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\sti_owner_review\RecordStatus;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;

/**
 * Recalculates owners' record status.
 */
final class OwnerReviewCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    private readonly RecordStatus $recordStatus,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct();
  }

  /**
   * Saves every owner whose record status differs from what its data says; the save sets the status.
   *
   * Needed once for owners saved before the status was derived; afterwards every save keeps it right.
   */
  #[CLI\Command(name: 'sti-owner:record-status')]
  #[CLI\Option(name: 'dry-run', description: 'Only list the owners whose status would change.')]
  #[CLI\Usage(name: 'drush sti-owner:record-status --dry-run', description: 'See which owners would change.')]
  public function recalculate(array $options = ['dry-run' => FALSE]): void {
    $storage = $this->entityTypeManager->getStorage('node');
    $changed = 0;
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition('type', 'owner')->execute();
    foreach (array_chunk($ids, 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $owner) {
        $current = (string) $owner->get(RecordStatus::FIELD)->entity?->label();
        $derived = $this->recordStatus->derive($owner);
        if ($current === $derived) {
          continue;
        }
        $this->output()->writeln(sprintf('%d %s: %s -> %s', $owner->id(), $owner->label(), $current ?: '(none)', $derived));
        if ($options['dry-run']) {
          continue;
        }
        $owner->setNewRevision(TRUE);
        $owner->setRevisionLogMessage('Record status recalculated.');
        $owner->save();
        // A missing term or a workflow hook would leave the old status; the save reports success either way.
        if ((string) $storage->loadUnchanged($owner->id())->get(RecordStatus::FIELD)->entity?->label() !== $derived) {
          $this->logger()->error(sprintf('Owner %d: status did not save as "%s". Is the term in sti_record_status (drush migrate:import sti_record_status_terms --update)? Stopped.', $owner->id(), $derived));
          return;
        }
        $changed++;
      }
      $storage->resetCache($chunk);
    }
    if ($options['dry-run']) {
      $this->logger()->notice('Dry run: nothing changed.');
      return;
    }
    $this->logger()->success(sprintf('%d owners updated.', $changed));
  }

}
