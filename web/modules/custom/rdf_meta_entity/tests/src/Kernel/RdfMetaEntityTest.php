<?php

declare(strict_types = 1);

namespace Drupal\Tests\rdf_meta_entity\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntityType;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntityTypeInterface;
use Drupal\rdf_meta_entity\RdfMetaEntityRepository;

/**
 * Tests rdf meta entity.
 *
 * @group rdf_meta_entity
 */
class RdfMetaEntityTest extends KernelTestBase {

  use SparqlConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dynamic_entity_reference',
    'entity_test',
    'field',
    'meta_entity',
    'rdf_meta_entity',
    'rdf_meta_entity_test',
    'sparql_entity_storage',
    'user',
  ];

  /**
   * The RDF meta entity repository.
   *
   * @var \Drupal\rdf_meta_entity\RdfMetaEntityRepository
   */
  protected RdfMetaEntityRepository $repository;

  /**
   * The RDF meta entity type entity.
   *
   * @var \Drupal\rdf_meta_entity\Entity\RdfMetaEntityTypeInterface
   */
  protected RdfMetaEntityTypeInterface $rdfMetaEntityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpSparql();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('meta_entity');
    $this->installEntitySchema('rdf_meta_entity');
    $this->installConfig(['rdf_meta_entity_test', 'sparql_entity_storage']);
    $this->repository = $this->container->get('rdf_meta_entity.repository');
    $this->rdfMetaEntityType = RdfMetaEntityType::load('visit_count');
  }

  /**
   * Tests meta entity auto-creation.
   */
  public function testAutoCreation(): void {
    // Set the mapping for but don't set any 'auto-*' flag.
    $this->rdfMetaEntityType->set('mapping', [
      'entity_test' => [
        'entity_test' => [],
      ],
    ])->save();

    $target_entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
    ]);
    $target_entity->save();
    // Check that no associated meta entity has been created.
    $this->assertNull($this->repository->getMetaEntityForEntity($target_entity, 'visit_count'));
    $target_entity->delete();

    // Add meta entity auto-creation for this bundle.
    $this->rdfMetaEntityType->set('mapping', [
      'entity_test' => [
        'entity_test' => [
          'auto_create' => TRUE,
        ],
      ],
    ])->save();

    $target_entity = EntityTest::create([
      'type' => 'entity_test',
      'name' => $this->randomString(),
    ]);
    $target_entity->save();

    // Check that an associated meta entity has been created.
    $meta_entity = $this->repository->getMetaEntityForEntity($target_entity, 'visit_count');
    $this->assertSame('visit_count', $meta_entity->bundle());

    // Check that the associated meta entity has been deleted.
    $target_entity->delete();
    $this->assertNull($this->repository->getMetaEntityForEntity($target_entity, 'visit_count'));
  }

}
