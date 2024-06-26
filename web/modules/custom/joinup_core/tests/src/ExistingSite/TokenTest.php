<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\ExistingSite;

use Drupal\node\Entity\Node;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\token\Functional\TokenTestTrait;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Tests the community content parent URL tokens.
 */
class TokenTest extends JoinupExistingSiteTestBase {

  use NodeCreationTrait;
  use TokenTestTrait;

  /**
   * Tests the parent_collection token.
   */
  public function testParentCollection(): void {
    $collection = $this->createRdfEntity([
      'rid' => 'collection',
      'title' => $this->randomMachineName(),
      'field_ar_state' => 'published',
    ]);

    $solution = $this->createRdfEntity([
      'rid' => 'solution',
      'title' => $this->randomMachineName(),
      'collection' => $collection,
      'field_is_state' => 'published',
    ]);

    $release = $this->createNode([
      'type' => 'release',
      'title' => $this->randomMachineName(),
      'is_version_of' => $solution,
      'field_state' => 'published',
    ]);

    $distribution = $this->createNode([
      'type' => 'distribution',
      'title' => $this->randomMachineName(),
      'og_audience' => $solution->id(),
      'field_state' => 'published',
    ]);

    // Check both the token itself and one of the recursively generated
    // tokens.
    $tokens = [
      'parent_collection' => $collection->label(),
      'parent_collection:rid:target_id' => 'collection',
    ];

    $data = ['rdf_entity' => $solution];
    $this->assertTokens('rdf_entity', $data, $tokens);

    $data = ['rdf_entity' => $release];
    $this->assertTokens('rdf_entity', $data, $tokens);

    $data = ['rdf_entity' => $distribution];
    $this->assertTokens('rdf_entity', $data, $tokens);
  }

  /**
   * Tests the 'short_id_or_title' token.
   *
   * @param string $bundle
   *   The entity bundle.
   * @param string $short_id
   *   The value of the short ID field.
   * @param string $title
   *   The title of the entity.
   * @param string $expected_token
   *   The expected token replacement for the 'short_id_or_title'.
   *
   * @dataProvider shortIdOrTitleDataProvider
   */
  public function testShortIdOrTitleToken(string $bundle, string $short_id, string $title, string $expected_token): void {
    $entity = Rdf::create([
      'rid' => $bundle,
      'label' => $title,
      'field_short_id' => $short_id,
    ]);

    $tokens = [
      'short_id_or_title' => $expected_token,
    ];
    $data = ['rdf_entity' => $entity];
    $this->assertTokens('rdf_entity', $data, $tokens);
  }

  /**
   * Tests that 'short_id_or_title' does not fail for bundles without short ID.
   */
  public function testShortIdOrTitleInvalidBundles(): void {
    $title = $this->randomMachineName();
    $entity = Node::create([
      'type' => 'release',
      'title' => $title,
    ]);

    $tokens = [
      'short_id_or_title' => $title,
    ];
    $data = ['node' => $entity];
    $this->assertTokens('node', $data, $tokens);
  }

  /**
   * Provides data for the testShortIdOrTitleToken method.
   */
  public function shortIdOrTitleDataProvider(): array {
    return [
      'solution with a short ID' => [
        'solution', 'some-short-id', 'some title', 'some-short-id',
      ],
      'solution without a short ID' => [
        'solution', '', 'some title', 'some title',
      ],
      'collection with a short ID' => [
        'collection', 'some-short-id', 'some title', 'some-short-id',
      ],
      'collection without a short ID' => [
        'collection', '', 'some title', 'some title',
      ],
    ];
  }

}
