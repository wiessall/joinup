<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;

/**
 * Behat step definitions for testing owners.
 */
class OwnerContext extends RawDrupalContext {

  use EntityTrait;
  use NodeTrait;
  use SearchTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Navigates to the canonical page display of an owner.
   *
   * @param string $owner
   *   The label of the owner.
   *
   * @When I go to (the homepage of )the :owner owner
   * @When I visit (the homepage of )the :owner owner
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitOwner(string $owner): void {
    /** @var \Drupal\node\Entity\Node $entity */
    $entity = $this->getEntityByLabel('node', $owner, 'owner');
    $this->visitPath($entity->toUrl()->toString());
  }

}
