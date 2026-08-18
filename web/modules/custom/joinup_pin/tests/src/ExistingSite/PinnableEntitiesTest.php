<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_pin\ExistingSite;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_news\Entity\NewsInterface;
use Drupal\joinup_search\SearchIndexCommitter;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests pinnable entities.
 */
#[Group('joinup_pin')]
class PinnableEntitiesTest extends JoinupExistingSiteTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
  }

  /**
   * Test getting groups for a pinned entity if the group has been deleted.
   *
   * This is a regression test for a fatal error that was occurring when content
   * was pinned in a collection and the collection was subsequently deleted. The
   * method was unexpectedly returning an array containing NULL values rather
   * than groups.
   */
  public function testGetGroupsWherePinnedWithDeletedGroup(): void {
    $collection = $this->createNode([
      'type' => 'collection',
      'title' => $this->getRandomGenerator()->string(),
      'field_state' => 'published',
    ]);
    assert($collection instanceof CollectionInterface);

    $news = $this->createNode([
      'type' => 'news',
      'title' => $this->randomString(),
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $collection->id(),
    ]);
    assert($news instanceof NewsInterface);

    // Pin the news article inside the collection.
    $news->pin($collection);

    // Check the groups where the entity is pinned. This should return an array
    // containing 1 single result: the test collection.
    $result = $this->getGroupsWherePinned($news);
    $this->assertCount(1, $result);

    $pinned_group = reset($result);
    $this->assertInstanceOf(CollectionInterface::class, $pinned_group);
    $this->assertEquals($collection->id(), $pinned_group->id());

    // Delete the collection.
    $collection->delete();

    // Check the groups where the entity is pinned again. This should now
    // return an empty array.
    $result = $this->getGroupsWherePinned($news);
    $this->assertEquals([], $result);
  }

  /**
   * Tests that pinned content is listed first on the group content listing.
   *
   * The listing is sorted by Solr, so the pinned status is only reflected once
   * the changed content has been indexed and committed. The pin and unpin
   * routes do that within the request that writes the change, which is what
   * keeps the listing coherent for the user that is redirected back to it.
   */
  public function testPinnedContentIsListedFirst(): void {
    $collection = $this->createNode([
      'type' => 'collection',
      'title' => $this->randomMachineName(),
      'field_state' => 'published',
    ]);
    assert($collection instanceof CollectionInterface);

    // The listing falls back to sorting on the creation date, descending, so
    // the older article is listed last as long as nothing is pinned.
    $older = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      'og_audience' => $collection->id(),
      'field_state' => 'published',
      'created' => 1500000000,
    ]);
    $newer = $this->createNode([
      'type' => 'news',
      'title' => $this->randomMachineName(),
      'og_audience' => $collection->id(),
      'field_state' => 'published',
      'created' => 1600000000,
    ]);
    assert($older instanceof NewsInterface);
    assert($newer instanceof NewsInterface);

    $committer = \Drupal::service(SearchIndexCommitter::class);
    $committer->commitPendingChanges();
    $this->assertSame([$newer->id(), $older->id()], $this->getListedContentIds($collection));

    // Pinning the older article brings it to the top. The entities are loaded
    // anew before each action because in production every one of them happens
    // in its own request.
    $this->reload($older)->pin($this->reload($collection));
    $committer->commitPendingChanges();
    $this->assertSame([$older->id(), $newer->id()], $this->getListedContentIds($collection));

    // Unpinning it restores the original order.
    $this->reload($older)->unpin($this->reload($collection));
    $committer->commitPendingChanges();
    $this->assertSame([$newer->id(), $older->id()], $this->getListedContentIds($collection));
  }

  /**
   * Returns a freshly loaded copy of the given node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to reload.
   *
   * @return \Drupal\node\NodeInterface
   *   The reloaded node.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    return $this->entityTypeManager->getStorage('node')->loadUnchanged($node->id());
  }

  /**
   * Returns the IDs of the given group's content, in the order they are listed.
   *
   * This mimics the query that the group content listing field performs, so
   * that the sorting applied by
   * \Drupal\joinup_search\EventSubscriber\SearchApiFieldListingOrder is
   * exercised.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group for which to return the listed content.
   *
   * @return string[]
   *   The node IDs, in the order they appear in the listing.
   */
  protected function getListedContentIds(GroupInterface $group): array {
    $query = Index::load('published')->query();
    $query->addTag('search_api_field_listing_content');
    $query->setOption('search_api_field entity', $group);
    $query->addCondition('entity_groups', [$group->id()], 'IN');

    $node_ids = [];
    foreach (array_keys($query->execute()->getResultItems()) as $item_id) {
      $node_ids[] = preg_replace('/^entity:node\/(\d+):.*$/', '$1', $item_id);
    }
    return array_values(array_unique($node_ids));
  }

  /**
   * Returns the groups where the given entity has been pinned.
   *
   * @param \Drupal\joinup_group\Entity\PinnableGroupContentInterface $entity
   *   The entity for which to return the groups.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface[]
   *   The groups where the entity has been pinned.
   */
  protected function getGroupsWherePinned(PinnableGroupContentInterface $entity): array {
    // Refresh the entity so that our test is not affected by static caches.
    $entity = $this->entityTypeManager->getStorage($entity->getEntityTypeId())->loadUnchanged($entity->id());
    $group_ids = $entity->getPinnedGroupIds();
    return $this->entityTypeManager->getStorage('node')->loadMultiple($group_ids);
  }

}
