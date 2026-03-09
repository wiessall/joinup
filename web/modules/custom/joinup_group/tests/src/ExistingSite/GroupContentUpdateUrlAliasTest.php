<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_group\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup\Traits\RandomGeneratorTrait;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drush\TestTraits\DrushTestTrait;
use PHPUnit\Framework\Attributes\Group;
use weitzman\LoginTrait\LoginTrait;

/**
 * Tests group content URL aliases update when the group short ID changes.
 */
#[Group('joinup_group')]
class GroupContentUpdateUrlAliasTest extends JoinupExistingSiteTestBase {

  use DrushTestTrait;
  use LoginTrait;
  use RandomGeneratorTrait;

  /**
   * Testing entities.
   *
   * @var \Drupal\node\NodeInterface[][]
   */
  protected array $node = [];

  /**
   * Tests group content URL aliases update when the group short ID changes.
   */
  public function testGroupContentUpdateUrlAlias(): void {
    $assert = $this->assertSession();

    $this->createContent();

    $distributions = [
      (int) $this->node['distribution']['1.1.1']->id(),
      (int) $this->node['distribution']['1.1.2']->id(),
      (int) $this->node['distribution']['1.2']->id(),
    ];
    $releases = [
      (int) $this->node['release']['1.1']->id(),
    ];
    $solutions = [
      (int) $this->node['solution']['1']->id(),
    ];

    // Check that the collection content is correctly computed and predictable.
    $this->assertSame([
      // The creation order is determined, same is the node IDs order.
      'node' => [
        'custom_page' => [
          (int) $this->node['custom_page']['1.1']->id(),
          (int) $this->node['custom_page']['1']->id(),
        ],
        'discussion' => [
          (int) $this->node['discussion']['1.1']->id(),
          (int) $this->node['discussion']['1']->id(),
        ],
        'distribution' => $distributions,
        'document' => [
          (int) $this->node['document']['1.1']->id(),
          (int) $this->node['document']['1']->id(),
        ],
        'event' => [
          (int) $this->node['event']['1.1']->id(),
          (int) $this->node['event']['1']->id(),
        ],
        'glossary' => [
          (int) $this->node['glossary']['1']->id(),
        ],
        'news' => [
          (int) $this->node['news']['1.1']->id(),
          (int) $this->node['news']['1']->id(),
        ],
        'release' => $releases,
        'solution' => $solutions,
      ],
    ], $this->node['collection']['1']->getGroupContentIds());

    // Check that the solution content is correctly computed and predictable.
    $this->assertSame([
      'node' => [
        'custom_page' => [
          (int) $this->node['custom_page']['1.1']->id(),
        ],
        'discussion' => [
          (int) $this->node['discussion']['1.1']->id(),
        ],
        'distribution' => $distributions,
        'document' => [
          (int) $this->node['document']['1.1']->id(),
        ],
        'event' => [
          (int) $this->node['event']['1.1']->id(),
        ],
        'news' => [
          (int) $this->node['news']['1.1']->id(),
        ],
        'release' => $releases,
      ],
    ], $this->node['solution']['1']->getGroupContentIds());

    $this->drupalLogin($this->createUser([], NULL, FALSE, [
      'roles' => ['moderator'],
    ]));

    // Edit the solution and add a short ID.
    $edit = ['short_id[0][value]' => 'abc123'];
    $this->drupalGet($this->node['solution']['1']->toUrl('edit-form'));
    array_walk($edit, static fn($value, $key) => $assert->fieldExists($key)->setValue($value));
    $this->getCurrentPage()->pressButton('Confirm');
    $this->submitForm([], 'Publish');
    $this->assertSession()
      ->pageTextContains("You've added a solution short ID: abc123. It will take some time until the solution content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);

    // Check that solution content URL aliases were updated.
    $this->drupalGet($this->node['solution']['1']->toUrl());
    $assert->addressEquals('/collection/big-collection/solution/abc123');
    // Releases.
    $this->assertEquals('/collection/big-collection/solution/abc123/release/11', $this->node['release']['1.1']->toUrl()
      ->toString());
    // Release 1.1 distributions.
    $this->assertEquals('/collection/big-collection/solution/abc123/distribution/distribution-111', $this->node['distribution']['1.1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/abc123/distribution/distribution-112', $this->node['distribution']['1.1.2']->toUrl()
      ->toString());
    // Solution distributions.
    $this->assertEquals('/collection/big-collection/solution/abc123/distribution/distribution-12', $this->node['distribution']['1.2']->toUrl()
      ->toString());
    // Solution nodes.
    $this->assertEquals('/collection/big-collection/solution/abc123/custompage-11', $this->node['custom_page']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/abc123/discussion/discussion-11', $this->node['discussion']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/abc123/document/document-11', $this->node['document']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/abc123/event/event-11', $this->node['event']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/abc123/news/news-11', $this->node['news']['1.1']->toUrl()
      ->toString());

    // Test updating the short ID of the collection.
    $edit = ['short_id[0][value]' => 'xyz789'];
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've added a collection short ID: xyz789. It will take some time until the collection content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);
    \Drupal::service('path_alias.manager')->cacheClear();

    $this->assertEquals('/collection/xyz789', $this->node['collection']['1']->toUrl()
      ->toString());
    // Solution.
    $this->assertEquals('/collection/xyz789/solution/abc123', $this->node['solution']['1']->toUrl()
      ->toString());
    // Releases.
    $this->assertEquals('/collection/xyz789/solution/abc123/release/11', $this->node['release']['1.1']->toUrl()
      ->toString());
    // Release 1.1 distributions.
    $this->assertEquals('/collection/xyz789/solution/abc123/distribution/distribution-111', $this->node['distribution']['1.1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/solution/abc123/distribution/distribution-112', $this->node['distribution']['1.1.2']->toUrl()
      ->toString());
    // Solution distributions.
    $this->assertEquals('/collection/xyz789/solution/abc123/distribution/distribution-12', $this->node['distribution']['1.2']->toUrl()
      ->toString());
    // Solution nodes.
    $this->assertEquals('/collection/xyz789/solution/abc123/custompage-11', $this->node['custom_page']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/solution/abc123/discussion/discussion-11', $this->node['discussion']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/solution/abc123/document/document-11', $this->node['document']['1.1']->toUrl()
      ->toString(),);
    $this->assertEquals('/collection/xyz789/solution/abc123/event/event-11', $this->node['event']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/solution/abc123/news/news-11', $this->node['news']['1.1']->toUrl()
      ->toString());
    // Collection's nodes.
    $this->assertEquals('/collection/xyz789/custompage-1', $this->node['custom_page']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/discussion/discussion-1', $this->node['discussion']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/document/document-1', $this->node['document']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/event/event-1', $this->node['event']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/glossary/term/glossary-1', $this->node['glossary']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/xyz789/news/news-1', $this->node['news']['1']->toUrl()
      ->toString());
    // Test the success message on short ID update.
    $edit = ['short_id[0][value]' => 'other-id'];
    $this->drupalGet($this->node['solution']['1']->toUrl('edit-form'));
    array_walk($edit, static fn($value, $key) => $assert->fieldExists($key)->setValue($value));
    $this->getCurrentPage()->pressButton('Confirm');
    $this->submitForm([], 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the solution's short ID from abc123 to other-id. It will take some time until the solution content URLs will be updated.");
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the collection's short ID from xyz789 to other-id. It will take some time until the collection content URLs will be updated.");

    // Test the short ID deletion.
    $edit = ['short_id[0][value]' => ''];
    $this->drupalGet($this->node['solution']['1']->toUrl('edit-form'));
    array_walk($edit, static fn($value, $key) => $assert->fieldExists($key)->setValue($value));
    $this->getCurrentPage()->pressButton('Confirm');
    $this->submitForm([], 'Publish');
    $this->assertSession()
      ->pageTextContains("You've removed the solution's short ID: other-id. It will take some time until the solution content URLs will be updated.");
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've removed the collection's short ID: other-id. It will take some time until the collection content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);
    \Drupal::service('path_alias.manager')->cacheClear();

    $this->assertEquals('/collection/big-collection', $this->node['collection']['1']->toUrl()
      ->toString());
    // Solution.
    $this->assertEquals('/collection/big-collection/solution/solution-1', $this->node['solution']['1']->toUrl()
      ->toString());
    // Releases.
    $this->assertEquals('/collection/big-collection/solution/solution-1/release/11', $this->node['release']['1.1']->toUrl()
      ->toString());
    // Release 1.1 distributions.
    $this->assertEquals('/collection/big-collection/solution/solution-1/distribution/distribution-111', $this->node['distribution']['1.1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/solution-1/distribution/distribution-112', $this->node['distribution']['1.1.2']->toUrl()
      ->toString());
    // Solution distributions.
    $this->assertEquals('/collection/big-collection/solution/solution-1/distribution/distribution-12', $this->node['distribution']['1.2']->toUrl()
      ->toString());
    // Solution nodes.
    $this->assertEquals('/collection/big-collection/solution/solution-1/custompage-11', $this->node['custom_page']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/solution-1/discussion/discussion-11', $this->node['discussion']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/solution-1/document/document-11', $this->node['document']['1.1']->toUrl()
      ->toString(),);
    $this->assertEquals('/collection/big-collection/solution/solution-1/event/event-11', $this->node['event']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/solution/solution-1/news/news-11', $this->node['news']['1.1']->toUrl()
      ->toString());
    // Collection's nodes.
    $this->assertEquals('/collection/big-collection/custompage-1', $this->node['custom_page']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/discussion/discussion-1', $this->node['discussion']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/document/document-1', $this->node['document']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/event/event-1', $this->node['event']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/glossary/term/glossary-1', $this->node['glossary']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/big-collection/news/news-1', $this->node['news']['1']->toUrl()
      ->toString());

    // Test updating the title of the collection.
    $edit = ['title[0][value]' => 'The Biggest Collection'];
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the collection's title from The Big Collection to The Biggest Collection. It will take some time until the collection content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);
    \Drupal::service('path_alias.manager')->cacheClear();

    $this->assertEquals('/collection/biggest-collection', $this->node['collection']['1']->toUrl()
      ->toString());
    // Solution.
    $this->assertEquals('/collection/biggest-collection/solution/solution-1', $this->node['solution']['1']->toUrl()
      ->toString());
    // Releases.
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/release/11', $this->node['release']['1.1']->toUrl()
      ->toString());
    // Release 1.1 distributions.
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/distribution/distribution-111', $this->node['distribution']['1.1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/distribution/distribution-112', $this->node['distribution']['1.1.2']->toUrl()
      ->toString());
    // Solution distributions.
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/distribution/distribution-12', $this->node['distribution']['1.2']->toUrl()
      ->toString());
    // Solution nodes.
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/custompage-11', $this->node['custom_page']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/discussion/discussion-11', $this->node['discussion']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/document/document-11', $this->node['document']['1.1']->toUrl()
      ->toString(),);
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/event/event-11', $this->node['event']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-1/news/news-11', $this->node['news']['1.1']->toUrl()
      ->toString());
    // Collection's nodes.
    $this->assertEquals('/collection/biggest-collection/custompage-1', $this->node['custom_page']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/discussion/discussion-1', $this->node['discussion']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/document/document-1', $this->node['document']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/event/event-1', $this->node['event']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/glossary/term/glossary-1', $this->node['glossary']['1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/news/news-1', $this->node['news']['1']->toUrl()
      ->toString());

    // Test updating the title of the solution.
    $edit = ['title[0][value]' => 'Solution one'];
    $this->drupalGet($this->node['solution']['1']->toUrl('edit-form'));
    array_walk($edit, static fn($value, $key) => $assert->fieldExists($key)->setValue($value));
    $this->getCurrentPage()->pressButton('Confirm');
    $this->submitForm([], 'Publish');
    $this->assertSession()
      ->pageTextContains("You've changed the solution's title from solution 1 to Solution one. It will take some time until the solution content URLs will be updated.");

    // Process both queues.
    $this->drush('queue:run', ['joinup_group:group_update']);
    $this->drush('queue:run', ['joinup_group:group_content_update']);
    \Drupal::service('path_alias.manager')->cacheClear();

    // Solution.
    $this->assertEquals('/collection/biggest-collection/solution/solution-one', $this->node['solution']['1']->toUrl()
      ->toString());
    // Releases.
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/release/11', $this->node['release']['1.1']->toUrl()
      ->toString());
    // Release 1.1 distributions.
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/distribution/distribution-111', $this->node['distribution']['1.1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/distribution/distribution-112', $this->node['distribution']['1.1.2']->toUrl()
      ->toString());
    // Solution distributions.
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/distribution/distribution-12', $this->node['distribution']['1.2']->toUrl()
      ->toString());
    // Solution nodes.
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/custompage-11', $this->node['custom_page']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/discussion/discussion-11', $this->node['discussion']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/document/document-11', $this->node['document']['1.1']->toUrl()
      ->toString(),);
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/event/event-11', $this->node['event']['1.1']->toUrl()
      ->toString());
    $this->assertEquals('/collection/biggest-collection/solution/solution-one/news/news-11', $this->node['news']['1.1']->toUrl()
      ->toString());

    // Test updating the short ID  of the collection without publishing.
    $edit = ['short_id[0][value]' => 'xyz789'];
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Propose');
    $this->assertSession()
      ->pageTextNotContains("You've added a collection short ID: xyz789. It will take some time until the collection content URLs will be updated.");
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Save as draft');
    $this->assertSession()
      ->pageTextNotContains("You've added a collection short ID: xyz789. It will take some time until the collection content URLs will be updated.");
    // Test updating the title of the collection without publishing.
    $edit = ['title[0][value]' => 'An Unchanged Collection'];
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Save as draft');
    $this->assertSession()
      ->pageTextNotContains("You've changed the collection's title from The Biggest Collection to An Unchanged Collection. It will take some time until the collection content URLs will be updated.");
    $this->drupalGet($this->node['collection']['1']->toUrl('edit-form'));
    $this->submitForm($edit, 'Propose');
    $this->assertSession()
      ->pageTextNotContains("You've changed the collection's title from The Biggest Collection to An Unchanged Collection. It will take some time until the collection content URLs will be updated.");
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

    $owner = $this->getDefaultOwner();
    $contact = $this->getDefaultContact();

    $this->node['collection']['1'] = $this->createNode([
      'type' => 'collection',
      'uri' => 'http://collection',
      'title' => 'The Big Collection',
      'abstract' => $this->randomString(),
      'description' => $this->randomString(),
      'field_state' => 'published',
      'owner' => $owner->id(),
      'field_topic' => $this->getRandomTerm('topic', TRUE),
      'contact' => $contact->id(),
    ]);

    $this->node['solution']['1'] = $this->createNode([
      'type' => 'solution',
      'uri' => "http://solution/1",
      'title' => "solution 1",
      'og_audience' => $this->node['collection']['1'],
      'abstract' => $this->randomString(),
      'description' => $this->randomString(),
      'classification' => $this->getRandomTerm('solution_classification'),
      'domain' => $this->getRandomTerm('domain'),
      'field_state' => 'published',
      'owner' => $owner->id(),
      'field_topic' => $this->getRandomTerm('topic', TRUE),
      'solution_type' => MappedTerm::loadByUri('http://data.europa.eu/dr8/testing/PublicServiceProvider')->id(),
      'language' => $this->getTermByName('language', 'English'),
      'contact' => $contact->id(),
    ]);
    // Releases.
    $this->node['release']["1.1"] = $this->createNode([
      'type' => 'release',
      'id' => "http://release/1.1",
      'title' => "release 1.1",
      'release_number' => "1.1",
      'og_audience' => $this->node['solution']['1'],
      'field_state' => 'published',
    ]);
    // Release distributions.
    for ($j = 1; $j <= 2; $j++) {
      $this->node['distribution']["1.1.{$j}"] = $this->createNode([
        'type' => 'distribution',
        'uri' => "http://distribution/1.1.{$j}",
        'title' => "distribution 1.1.{$j}",
        'release' => $this->node['release']["1.1"]->id(),
        'field_state' => 'published',
      ]);
    }

    // Solution standalone distributions.
    $this->node['distribution']["1.2"] = $this->createNode([
      'type' => 'distribution',
      'title' => "distribution 1.2",
      'og_audience' => $this->node['solution']['1'],
      'field_state' => 'published',
    ]);

    // Solution nodes.
    foreach ($solution_node_bundles as $bundle) {
      $this->node[$bundle]["1.1"] = $this->createNode([
        'type' => $bundle,
        'title' => "{$bundle} 1.1",
        'og_audience' => $this->node['solution']['1'],
        'field_state' => 'published',
      ]);
    }

    // Collection nodes.
    foreach ($collection_node_bundles as $bundle) {
      $this->node[$bundle]["1"] = $this->createNode([
        'type' => $bundle,
        'title' => "{$bundle} 1",
        'og_audience' => $this->node['collection']['1'],
        'field_state' => 'published',
      ]);
    }
  }

}
