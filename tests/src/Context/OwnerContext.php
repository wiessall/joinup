<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;

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
   * Checks the number of available owners.
   *
   * @param int $number
   *   The expected number of owners.
   *
   * @throws \Exception
   *   Throws an exception when the expected number is not equal to the given.
   *
   * @Then I should have :number owner/owners
   */
  public function assertOwnerCount(int $number): void {
    $this->assertEntityCount('node', 'owner', $number);
  }

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
