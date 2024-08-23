<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_sparql\Kernel;

use Drupal\Core\Database\Query\Query;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\sparql_entity_storage\Entity\Query\Sparql\SparqlQueryInterface;

/**
 * Tests the default graphs event subscriber.
 *
 * @group joinup_sparql
 */
class JoinupSparqlDefaultGraphsTest extends KernelTestBase {

  use SparqlConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'joinup_sparql',
    'joinup_sparql_test',
    'sparql_entity_storage',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function bootEnvironment() {
    parent::bootEnvironment();
    $this->setUpSparql();
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'joinup_sparql_test',
      'rdf_draft',
      'sparql_entity_storage',
    ]);
  }

  /**
   * Tests the default graphs event subscriber.
   */
  public function testDefaultGraphs(): void {
    $id = 'http://example.com/apple';
    Node::create([
      'uri' => $id,
      'type' => 'fruit',
      'title' => 'Apple',
      'graph' => 'arbitrary',
    ])->save();

    // Check that loading explicitly from a candidate list, that includes the
    // 'arbitrary' graph, works.
    // @todo Change this to include the rdf sync mapper.
    $this->assertNotNull(Rdf::load($id, ['draft', 'arbitrary']));
    // Same for entity query.
    $ids = $this->getQuery()
      ->graphs(['draft', 'arbitrary'])
      ->condition('id', $id)
      ->execute();
    $this->assertCount(1, $ids);
    $this->assertEquals($id, reset($ids));

    // Check that loading the entity from the default graphs returns NULL.
    // @todo Change this to include the rdf sync mapper.
    $this->assertNull(Rdf::load($id));
    // Same for entity query.
    $ids = $this->getQuery()->condition('id', $id)->execute();
    $this->assertEmpty($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    // @todo Change this to include the rdf sync mapper.
    Rdf::load('http://example.com/apple', ['arbitrary'])->delete();
    parent::tearDown();
  }

  /**
   * Returns the entity query.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The entity query.
   */
  protected function getQuery(): QueryInterface {
    return $this->container
      ->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery();
  }

}
