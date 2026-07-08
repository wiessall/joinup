<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_solution\ExistingSite;

use Drupal\joinup\Traits\CommonTrait;
use Drupal\joinup\Traits\RandomGeneratorTrait;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\CoversFunction;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the computation of the related solutions list.
 */
#[Group('joinup_solution')]
#[CoversFunction('joinup_solution_get_related_solutions_ids')]
class RelatedSolutionsTest extends JoinupExistingSiteTestBase {

  use CommonTrait;
  use RandomGeneratorTrait;

  /**
   * The related solutions list must be keyed by node ID, not revision ID.
   */
  public function testRelatedSolutionsKeyedByNodeId(): void {
    $owner = $this->getDefaultOwner();
    $contact = $this->getDefaultContact();
    $solution_type = MappedTerm::loadByUri('http://data.europa.eu/dr8/testing/PublicServiceProvider')->id();

    $collection = $this->createNode([
      'type' => 'collection',
      'title' => $this->randomMachineName(),
      'field_state' => 'published',
      'owner' => $owner->id(),
      'field_topic' => $this->getRandomTerm('topic', TRUE),
      'contact' => $contact->id(),
    ]);

    $values = [
      'type' => 'solution',
      'og_audience' => $collection,
      'abstract' => $this->randomString(),
      'description' => $this->randomString(),
      'field_state' => 'published',
      'owner' => $owner->id(),
      'field_topic' => $this->getRandomTerm('topic', TRUE),
      'solution_type' => $solution_type,
      'contact' => $contact->id(),
    ];

    // A published sibling that shares the EIRA building block (solution type).
    $sibling = $this->createNode(['title' => $this->randomMachineName()] + $values);
    // Give it a revision ID that differs from its node ID, so the revision-ID
    // keying of the entity query is actually exercised.
    $sibling->setNewRevision(TRUE);
    $sibling->save();
    $this->assertNotSame((int) $sibling->id(), (int) $sibling->getRevisionId());

    // The viewed solution lists solutions related by EIRA building block. That
    // flag lives on the solution's "settings" meta entity.
    $viewed = $this->createNode(['title' => $this->randomMachineName()] + $values);
    $meta = MetaEntity::loadOrCreate('solution_settings', $viewed);
    $meta->set('list_related_entities', 1);
    $meta->save();
    $this->markEntityForCleanup($meta);

    $ids = joinup_solution_get_related_solutions_ids($viewed);

    // The list is keyed by node id. A node entity query is keyed by revision
    // id, and before the fix those keys mixed into the list; on a revision-id /
    // node-id collision the self-exclusion then dropped an unrelated solution.
    foreach ($ids as $key => $value) {
      $this->assertSame((int) $key, (int) $value, 'Keyed by node id.');
    }
    // The sibling is present and the current solution is excluded.
    $this->assertContains((int) $sibling->id(), array_map('intval', $ids));
    $this->assertArrayNotHasKey((int) $viewed->id(), $ids);
  }

}
