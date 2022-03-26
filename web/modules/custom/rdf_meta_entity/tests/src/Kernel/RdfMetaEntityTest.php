<?php

declare(strict_types = 1);

namespace Drupal\Tests\rdf_meta_entity\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\entity_test\Entity\EntityTest;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntity;
use Drupal\rdf_meta_entity\Entity\RdfMetaEntityType;
use Drupal\user\Entity\Role;

/**
 * Tests rdf meta entity.
 *
 * @group rdf_meta_entity
 */
class RdfMetaEntityTest extends KernelTestBase {

  use SparqlConnectionTrait;
  use UserCreationTrait;

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
    'system',
    'user',
  ];

  /**
   * The RDF meta entity repository.
   *
   * @var \Drupal\rdf_meta_entity\RdfMetaEntityRepository
   */
  protected $repository;

  /**
   * The RDF meta entity type entity.
   *
   * @var \Drupal\meta_entity\Entity\MetaEntityTypeInterface
   */
  protected $rdfMetaEntityType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->setUpSparql();

    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('user');
    $this->installEntitySchema('meta_entity');
    $this->installEntitySchema('rdf_meta_entity');
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
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

  /**
   * Tests the meta-entity access handler.
   */
  public function testAccess(): void {
    $this->rdfMetaEntityType->set('mapping', [
      'entity_test' => [
        'entity_test' => [],
      ],
    ])->save();

    $rdf_meta_entity = RdfMetaEntity::create([
      'type' => 'visit_count',
      'target' => EntityTest::create([
        'type' => 'entity_test',
        'name' => $this->randomString(),
      ]),
    ]);
    $rdf_meta_entity->save();

    /** @var \Drupal\user\RoleInterface $role */
    $role = Role::create(['id' => $this->randomMachineName()]);
    $role->save();

    // User account with no permissions.
    $account = $this->createUser([], NULL, FALSE, [
      'roles' => [$role],
      // Force the 'uid' to 2 to avoid creating the superuser.
      'uid' => 2,
    ]);

    // The user has no access.
    $this->assertFalse($rdf_meta_entity->access('create', $account));
    $this->assertFalse($rdf_meta_entity->access('view', $account));
    $this->assertFalse($rdf_meta_entity->access('update', $account));
    $this->assertFalse($rdf_meta_entity->access('delete', $account));

    $role
      ->grantPermission('create visit_count rdf-meta-entity')
      ->grantPermission('view visit_count rdf-meta-entity')
      ->grantPermission('update visit_count rdf-meta-entity')
      ->grantPermission('delete visit_count rdf-meta-entity')
      ->save();

    // Reset internal static cache and test again.
    $this->container->get('entity_type.manager')->getAccessControlHandler('rdf_meta_entity')->resetCache();
    $this->assertTrue($rdf_meta_entity->access('create', $account));
    $this->assertTrue($rdf_meta_entity->access('view', $account));
    $this->assertTrue($rdf_meta_entity->access('update', $account));
    $this->assertTrue($rdf_meta_entity->access('delete', $account));

    // Revoke all previous permissions but grant 'administer meta entity'.
    $role
      ->revokePermission('create visit_count meta-entity')
      ->revokePermission('view visit_count meta-entity')
      ->revokePermission('update visit_count meta-entity')
      ->revokePermission('delete visit_count meta-entity')
      ->grantPermission('administer meta entity')
      ->save();

    // Reset internal static cache and test again.
    $this->container->get('entity_type.manager')->getAccessControlHandler('rdf_meta_entity')->resetCache();
    $this->assertTrue($rdf_meta_entity->access('create', $account));
    $this->assertTrue($rdf_meta_entity->access('view', $account));
    $this->assertTrue($rdf_meta_entity->access('update', $account));
    $this->assertTrue($rdf_meta_entity->access('delete', $account));

    // Clean up the entity.
    $rdf_meta_entity->delete();
  }

}
