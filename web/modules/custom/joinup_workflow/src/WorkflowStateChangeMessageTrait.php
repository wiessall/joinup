<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Trait for constructing revision log message for programmatic state changes.
 */
trait WorkflowStateChangeMessageTrait {

  /**
   * Constructs the revision log message for the newly saved entity version.
   *
   * @param \Drupal\joinup_workflow\ArchivableEntityInterface $entity
   *   The entity.
   * @param string $newState
   *   The new workflow state ID of the entity.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup|null $performer
   *   The performer (a queue worker, action plugin) to be referred in the
   *   log message. The current class is used if this parameter is omitted.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The revision log message for the newly saved entity version, or NULL if
   *   the provided entity is not revisionable.
   */
  protected function workflowStateChangeRevisionLogMessage(
    ArchivableEntityInterface $entity,
    string $newState,
    string|TranslatableMarkup|null $performer = NULL,
  ): ?TranslatableMarkup {
    if (
      !$entity instanceof RevisionableInterface ||
      !$entity->getEntityType()->isRevisionable()
    ) {
      return NULL;
    }

    return $this->t(
      "Updated state to '@state-new' by @performer, from revision ID '@revision-id-original' with state '@state-original'.",
      [
        '@performer' => $performer ?? __CLASS__,
        '@revision-id-original' => $entity->getRevisionId(),
        '@state-original' => $entity->getWorkflowState(),
        '@state-new' => $newState,
      ]
    );
  }

}
