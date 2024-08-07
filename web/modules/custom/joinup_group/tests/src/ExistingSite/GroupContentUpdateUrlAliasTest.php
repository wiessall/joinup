<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\ExistingSite;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\topic\Entity\Topic;
use Drush\TestTraits\DrushTestTrait;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests group content URL aliases update when the group short ID changes.
 *
 * @group joinup_group
 */
class GroupContentUpdateUrlAliasTest extends JoinupExistingSiteTestBase {

  use DrushTestTrait;
  use LoginTrait;

  /**
   * Testing entities.
   *
   * @var \Drupal\Core\Entity\ContentEntityInterface[][][]
   */
  protected array $entity = [];

  /**
   * Tests group content URL aliases update when the group short ID changes.
   */
  public function testGroupContentUpdateUrlAlias(): void {
    $assert = $this->assertSession();

    $this->createContent();

    $distributions = [
      (int) $this->entity['node']['distribution']['1.2.2']->id(),
      (int) $this->entity['node']['distribution']['1.1']->id(),
      (int) $this->entity['node']['distribution']['1.1.1']->id(),
      (int) $this->entity['node']['distribution']['1.2']->id(),
      (int) $this->entity['node']['distribution']['1.2.1']->id(),
      (int) $this->entity['node']['distribution']['1.1.2']->id(),
    ];
    sort($distributions);

    $releases = [
      (int) $this->entity['node']['release']['1.1']->id(),
      (int) $this->entity['node']['release']['1.2']->id(),
    ];
    sort($releases);

    // Check that the collection content is correctly computed and predictable.
    $this->assertSame([
      // The creation order is determined, same is the node IDs order.
      'node' => [
        'custom_page' => [
          (int) $this->entity['node']['custom_page']['1.1']->id(),
          (int) $this->entity['node']['custom_page']['1.2']->id(),
          (int) $this->entity['node']['custom_page']['1']->id(),
          (int) $this->entity['node']['custom_page']['2']->id(),
        ],
        'discussion' => [
          (int) $this->entity['node']['discussion']['1.1']->id(),
          (int) $this->entity['node']['discussion']['1.2']->id(),
          (int) $this->entity['node']['discussion']['1']->id(),
          (int) $this->entity['node']['discussion']['2']->id(),
        ],
        'distribution' => $distributions,
        'document' => [
          (int) $this->entity['node']['document']['1.1']->id(),
          (int) $this->entity['node']['document']['1.2']->id(),
          (int) $this->entity['node']['document']['1']->id(),
          (int) $this->entity['node']['document']['2']->id(),
        ],
        'event' => [
          (int) $this->entity['node']['event']['1.1']->id(),
          (int) $this->entity['node']['event']['1.2']->id(),
          (int) $this->entity['node']['event']['1']->id(),
          (int) $this->entity['node']['event']['2']->id(),
        ],
        'glossary' => [
          (int) $this->entity['node']['glossary']['1']->id(),
          (int) $this->entity['node']['glossary']['2']->id(),
        ],
        'news' => [
          (int) $this->entity['node']['news']['1.1']->id(),
          (int) $this->entity['node']['news']['1.2']->id(),
          (int) $this->entity['node']['news']['1']->id(),
          (int) $this->entity['node']['news']['2']->id(),
        ],
        'release' => $releases,
      ],
      'rdf_entity' => [
        'solution' => [
          'http://solution/1',
        ],
      ],
    ], $this->entity['rdf_entity']['collection']['1']->getGroupContentIds());

    // Check that the solution content is correctly computed and predictable.
    $this->assertSame([
      'node' => [
        'custom_page' => [
          (int) $this->entity['node']['custom_page']['1.1']->id(),
          (int) $this->entity['node']['custom_page']['1.2']->id(),
        ],
        'discussion' => [
          (int) $this->entity['node']['discussion']['1.1']->id(),
          (int) $this->entity['node']['discussion']['1.2']->id(),
        ],
        'distribution' => $distributions,
        'document' => [
          (int) $this->entity['node']['document']['1.1']->id(),
          (int) $this->entity['node']['document']['1.2']->id(),
        ],
        'event' => [
          (int) $this->entity['node']['event']['1.1']->id(),
          (int) $this->entity['node']['event']['1.2']->id(),
        ],
        'news' => [
          (int) $this->entity['node']['news']['1.1']->id(),
          (int) $this->entity['node']['news']['1.2']->id(),
        ],
        'release' => $releases,
      ],
    ], $this->entity['rdf_entity']['solution']['1']->getGroupContentIds());

    $this->drupalLogin($this->createUser([], NULL, FALSE, [
      'roles' => ['moderator'],
    ]));

    // Edit the solution and add a short ID.
    $edit = ['short_id[0][value]' => 'abc123'];
    $this->drupalGet($this->entity['rdf_entity']['solution']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've added a solution short ID: abc123. It will take some time until the solution content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);

    // Check that solution content URL aliases were updated.
    $this->drupalGet($this->entity['rdf_entity']['solution']['1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123');
    // Releases.
    $this->drupalGet($this->entity['node']['release']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/release/11');
    $this->drupalGet($this->entity['node']['release']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/release/12');
    // Release 1.1 distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-111');
    $this->drupalGet($this->entity['node']['distribution']['1.1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-112');
    // Release 1.2 distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.2.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-121');
    $this->drupalGet($this->entity['node']['distribution']['1.2.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-122');
    // Solution distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-11');
    $this->drupalGet($this->entity['node']['distribution']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/distribution/distribution-12');
    // Solution nodes.
    $this->drupalGet($this->entity['node']['custom_page']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/custompage-11');
    $this->drupalGet($this->entity['node']['custom_page']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/custompage-12');
    $this->drupalGet($this->entity['node']['discussion']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/discussion/discussion-11');
    $this->drupalGet($this->entity['node']['discussion']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/discussion/discussion-12');
    $this->drupalGet($this->entity['node']['document']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/document/document-11');
    $this->drupalGet($this->entity['node']['document']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/document/document-12');
    $this->drupalGet($this->entity['node']['event']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/event/event-11');
    $this->drupalGet($this->entity['node']['event']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/event/event-12');
    $this->drupalGet($this->entity['node']['news']['1.1']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/news/news-11');
    $this->drupalGet($this->entity['node']['news']['1.2']->toUrl());
    $assert->addressEquals('/collection/collection/solution/abc123/news/news-12');

    // Edit the the collection.
    $edit = ['short_id[0][value]' => 'xyz789'];
    $this->drupalGet($this->entity['rdf_entity']['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've added a collection short ID: xyz789. It will take some time until the collection content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);

    // Check that group content URL aliases were updated.
    $this->drupalGet($this->entity['rdf_entity']['collection']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789');
    // Solution.
    $this->drupalGet($this->entity['rdf_entity']['solution']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123');
    // Releases.
    $this->drupalGet($this->entity['node']['release']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/release/11');
    $this->drupalGet($this->entity['node']['release']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/release/12');
    // Release 1.1 distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-111');
    $this->drupalGet($this->entity['node']['distribution']['1.1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-112');
    // Release 1.2 distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.2.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-121');
    $this->drupalGet($this->entity['node']['distribution']['1.2.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-122');
    // Solution distributions.
    $this->drupalGet($this->entity['node']['distribution']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-11');
    $this->drupalGet($this->entity['node']['distribution']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/distribution/distribution-12');
    // Solution nodes.
    $this->drupalGet($this->entity['node']['custom_page']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/custompage-11');
    $this->drupalGet($this->entity['node']['custom_page']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/custompage-12');
    $this->drupalGet($this->entity['node']['discussion']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/discussion/discussion-11');
    $this->drupalGet($this->entity['node']['discussion']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/discussion/discussion-12');
    $this->drupalGet($this->entity['node']['document']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/document/document-11');
    $this->drupalGet($this->entity['node']['document']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/document/document-12');
    $this->drupalGet($this->entity['node']['event']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/event/event-11');
    $this->drupalGet($this->entity['node']['event']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/event/event-12');
    $this->drupalGet($this->entity['node']['news']['1.1']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/news/news-11');
    $this->drupalGet($this->entity['node']['news']['1.2']->toUrl());
    $assert->addressEquals('/collection/xyz789/solution/abc123/news/news-12');
    $this->drupalGet($this->entity['node']['custom_page']['1']->toUrl());
    // Collection's nodes.
    $assert->addressEquals('/collection/xyz789/custompage-1');
    $this->drupalGet($this->entity['node']['custom_page']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/custompage-2');
    $this->drupalGet($this->entity['node']['discussion']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/discussion/discussion-1');
    $this->drupalGet($this->entity['node']['discussion']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/discussion/discussion-2');
    $this->drupalGet($this->entity['node']['document']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/document/document-1');
    $this->drupalGet($this->entity['node']['document']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/document/document-2');
    $this->drupalGet($this->entity['node']['event']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/event/event-1');
    $this->drupalGet($this->entity['node']['event']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/event/event-2');
    $this->drupalGet($this->entity['node']['glossary']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/glossary/term/glossary-1');
    $this->drupalGet($this->entity['node']['glossary']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/glossary/term/glossary-2');
    $this->drupalGet($this->entity['node']['news']['1']->toUrl());
    $assert->addressEquals('/collection/xyz789/news/news-1');
    $this->drupalGet($this->entity['node']['news']['2']->toUrl());
    $assert->addressEquals('/collection/xyz789/news/news-2');

    // Test the success message on short ID update.
    $edit = ['short_id[0][value]' => 'other-id'];
    $this->drupalGet($this->entity['rdf_entity']['solution']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the solution's short ID from abc123 to other-id. It will take some time until the solution content URLs will be updated.");
    $this->drupalGet($this->entity['rdf_entity']['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the collection's short ID from xyz789 to other-id. It will take some time until the collection content URLs will be updated.");

    // Test the success message on short ID delete.
    $edit = ['short_id[0][value]' => ''];
    $this->drupalGet($this->entity['rdf_entity']['solution']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've removed the solution's short ID: other-id. It will take some time until the solution content URLs will be updated.");
    $this->drupalGet($this->entity['rdf_entity']['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've removed the collection's short ID: other-id. It will take some time until the collection content URLs will be updated.");
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $this->drush('queue:delete', ['joinup_group:group_update']);
    $this->drush('queue:delete', ['joinup_group:group_content_update']);
    parent::tearDown();
  }

  /**
   * Creates testing content.
   */
  protected function createContent(): void {
    $solution_node_bundles = [
      'custom_page',
      'discussion',
      'document',
      'event',
      'news',
    ];
    $collection_node_bundles = array_merge($solution_node_bundles, ['glossary']);

    $owner = $this->createNode([
      'type' => 'owner',
      'title' => $this->randomString(),
      'owner_type' => \Drupal::getContainer()
        ->get('rdf_sync.mapper')
        ->getEntityByUri('http://purl.org/adms/publishertype/SupraNationalAuthority'),
    ]);
    $contact = $this->createNode([
      'type' => 'contact_information',
      'title' => $this->randomString(),
      'email' => $this->randomMachineName() . '@example.com',
    ]);

    $this->entity['rdf_entity']['collection']['1'] = $this->createRdfEntity([
      'rid' => 'collection',
      'id' => 'http://collection',
      'label' => 'collection',
      'field_ar_abstract' => $this->randomString(),
      'field_ar_description' => $this->randomString(),
      'field_ar_state' => 'published',
      'field_ar_owner' => $owner->id(),
      'field_topic' => Topic::loadByUri('http://joinup.eu/ontology/topic#finance-in-eu'),
      'field_ar_contact_information' => $contact->id(),
    ]);

    $this->entity['rdf_entity']['solution']['1'] = $this->createRdfEntity([
      'rid' => 'solution',
      'id' => "http://solution/1",
      'label' => "solution 1",
      'collection' => 'http://collection',
      'field_is_abstract' => $this->randomString(),
      'field_is_description' => $this->randomString(),
      'field_is_state' => 'published',
      'field_is_owner' => $owner->id(),
      'field_topic' => Topic::loadByUri('http://joinup.eu/ontology/topic#finance-in-eu')->id(),
      'field_is_solution_type' => MappedTerm::loadByUri('http://data.europa.eu/dr8/PublicServiceProvider')->id(),
      'field_is_contact_information' => $contact->id(),
    ]);
    // Releases.
    for ($i = 1; $i <= 2; $i++) {
      $this->entity['node']['release']["1.{$i}"] = $this->createNode([
        'type' => 'release',
        'id' => "http://release/1.{$i}",
        'title' => "release 1.{$i}",
        'release_number' => "1.{$i}",
        'og_audience' => 'http://solution/1',
        'field_state' => 'published',
      ]);
      // Release distributions.
      for ($j = 1; $j <= 2; $j++) {
        $this->entity['node']['distribution']["1.{$i}.{$j}"] = $this->createNode([
          'type' => 'distribution',
          'uri' => "http://distribution/1.{$i}.{$j}",
          'title' => "distribution 1.{$i}.{$j}",
          'release' => $this->entity['node']['release']["1.{$i}"]->id(),
          'field_state' => 'published',
        ]);
      }
    }
    // Solution standalone distributions.
    for ($i = 1; $i <= 2; $i++) {
      $this->entity['node']['distribution']["1.{$i}"] = $this->createNode([
        'type' => 'distribution',
        'title' => "distribution 1.{$i}",
        'og_audience' => 'http://solution/1',
        'field_state' => 'published',
      ]);
    }
    // Solution nodes.
    for ($i = 1; $i <= 2; $i++) {
      foreach ($solution_node_bundles as $bundle) {
        $this->entity['node'][$bundle]["1.{$i}"] = $this->createNode([
          'type' => $bundle,
          'title' => "{$bundle} 1.{$i}",
          'og_audience' => 'http://solution/1',
          'field_state' => 'published',
        ]);
      }
    }
    // Collection nodes.
    for ($i = 1; $i <= 2; $i++) {
      foreach ($collection_node_bundles as $bundle) {
        $this->entity['node'][$bundle]["{$i}"] = $this->createNode([
          'type' => $bundle,
          'title' => "{$bundle} {$i}",
          'og_audience' => 'http://collection',
          'field_state' => 'published',
        ]);
      }
    }

    $this->reloadEntities();
  }

  /**
   * Reload all testing entities.
   *
   * Used to refresh the entity reference fields.
   */
  protected function reloadEntities(): void {
    array_walk($this->entity, function (array $bundles, string $entity_type_id): void {
      array_walk($bundles, function (array $entities, string $bundle) use ($entity_type_id): void {
        array_walk($entities, function (ContentEntityInterface $entity, string $index) use ($entity_type_id, $bundle): void {
          $this->entity[$entity_type_id][$bundle][$index] = \Drupal::entityTypeManager()
            ->getStorage($entity->getEntityTypeId())
            ->load($entity->id());
        });
      });
    });
  }

}
