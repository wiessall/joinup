<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_pin\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_news\Entity\NewsInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
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
