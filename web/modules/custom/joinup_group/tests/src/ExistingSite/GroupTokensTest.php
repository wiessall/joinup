<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\token\Functional\TokenTestTrait;

/**
 * Tests the rdf entity tokens for solutions, releases and distributions.
 */
class GroupTokensTest extends JoinupExistingSiteTestBase {

  use TokenTestTrait;

  /**
   * Tests tokens defined by joinup_group.
   */
  public function testGroupDefinedTokens(): void {
    $collection = $this->createNode([
      'type' => 'collection',
      'title' => $this->randomString(),
      'field_state' => 'published',
    ]);

    $solution = $this->createNode([
      'type' => 'solution',
      'field_state' => 'published',
      'title' => $this->randomString(),
      'og_audience' => $collection->id(),
    ]);

    $standalone_distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
      'field_state' => 'published',
    ]);

    $release = $this->createNode([
      'type' => 'release',
      'og_audience' => $solution->id(),
    ]);

    $release_distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomString(),
      'og_audience' => $solution->id(),
      'field_state' => 'published',
      'release' => $release->id(),
    ]);

    $entities_to_test = [
      'node' => [
        $release,
        $release_distribution,
        $standalone_distribution,
        $solution,
      ],
    ];

    $tokens = [
      'parent_collection' => $collection->label(),
      'parent_collection:id:value' => $collection->id(),
      'parent_collection:label:value' => $collection->label(),
      // URL::toString generates an aliased path.
      'parent_collection:url' => $collection->toUrl('canonical')->toString(),
    ];

    foreach ($entities_to_test as $entity_type_id => $entities) {
      foreach ($entities as $entity) {
        $data = [$entity_type_id => $entity];
        $this->assertTokens($entity_type_id, $data, $tokens);
      }
    }
  }

}
