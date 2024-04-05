<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
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
   * Creates one or many owners with data provided in a table.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | type                        | name        | id                      | state        |
   * | Company,Industry consortium | Acme Inc.   | http://example.com/path | published    |
   * | Local authority             | Gotham City |                         | needs_update |
   * | Private Individual(s)       | Jane Roe    |                         |              |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $owners_table
   *   The owners table.
   *
   * @throws \Exception;
   *   If a wrong type has been passed or if the author cannot be loaded.
   *
   * @Given (the following )owner(s):
   */
  public function givenOwner(TableNode $owners_table): void {
    foreach ($owners_table->getHash() as $row) {
      // If no type was specified, fallback to 'Private Individual(s)'.
      $types = isset($row['type']) ? array_map('trim', explode(',', $row['type'])) : ['Private Individual(s)'];

      $query = \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE);
      $tids = $query
        ->condition('vid', 'owner_type')
        ->condition('name', $types, 'IN')
        ->execute();

      $found = array_map(function (TermInterface $term) {
        return $term->label();
      }, Term::loadMultiple($tids));

      if ($not_found = array_diff($types, $found)) {
        throw new \Exception('Non-existent owner types: ' . implode(', ', $not_found));
      }

      $values = [
        'type' => 'owner',
        'owner_type' => implode(',', $types),
        'owner_name' => !empty($row['name']) ? $row['name'] : $this->getRandom()->name(8, TRUE),
      ];

      if (!empty($row['author'])) {
        $values['uid'] = $this->getUserByName($row['author'])->id();
      }

      $this->nodeCreate((object) $values);
    }
  }

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
    $this->assertNodeEntityCount($number, 'owner');
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
