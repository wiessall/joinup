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
   *
   * @When I visit the replicate path of the :entityLabel content
   */
  public function visitReplicationPath(string $entityLabel): void {
    $entity = $this->getEntityByLabel('node', $entityLabel);
    $canonical = (string) $entity->toUrl()->toString();
    $this->visitPath($canonical . '/replicate');
  }

}
