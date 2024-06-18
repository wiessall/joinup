<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;

/**
 * Context for Replicate UI related tests.
 */
class ReplicateContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Navigates to the replicate path an entity.
   *
   * @param string $entityLabel
   *   The label of the entity.
   * @param string $entityType
   *   The type of the entity.
   *
   * @When I visit the replicate path of the :entityLabel :entityType
   */
  public function visitSolution(string $entityLabel, string $entityType): void {
    $entityType = match ($entityType) {
      'content' => 'node',
      'rdf entity', 'rdf' => 'rdf_entity',
      default => $entityType,
    };
    $entity = $this->getEntityByLabel($entityType, $entityLabel);
    $canonical = (string) $entity->toUrl()->toString();
    $this->visitPath($canonical . '/replicate');
  }

}
