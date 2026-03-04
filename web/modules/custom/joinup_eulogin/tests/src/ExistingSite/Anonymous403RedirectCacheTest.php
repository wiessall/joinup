<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_eulogin\ExistingSite;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\Tests\joinup_test\Traits\NodeCreationTrait;

/**
 * Tests the 403 redirection cache.
 *
 * After an unpublished entity is saved, an anonymous, trying to access the
 * entity's canonical URL should receive am access denied (403) response. But,
 * the 'joinup_eulogin.anonymous_403.subscriber' subscriber redirects all
 * anonymous access denied pages pages to EU Login login page. This redirect
 * response is cached. This test makes sure that the redirect cache is correctly
 * invalidated when the entity visibility changes.
 *
 *
 * @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginAnonymous403Subscriber
 */
#[Group('joinup_eulogin')]
class Anonymous403RedirectCacheTest extends JoinupExistingSiteTestBase {

  use NodeCreationTrait;

  /**
   * Tests the 403 redirection cache.
   */
  public function testRedirectCache(): void {
    foreach ($this->getEntities() as $field_name => $entities) {
      foreach ($entities as $entity) {
        // Visit the unpublished entity to warm-up the redirect cache.
        $this->drupalGet($entity->toUrl());
        $this->assertSession()->pageTextContains('Sign in to continue');

        // Publish the entity.
        $entity->set($field_name, 'published')->save();

        // Revisit the entity. It should be visible.
        $this->drupalGet($entity->toUrl());
        $this->assertSession()->pageTextNotContains('Sign in to continue');
        $this->assertSession()->pageTextContains($entity->label());
        $this->assertSession()->addressEquals($entity->toUrl());
      }
    }
  }

  /**
   * Returns a list of testing entities.
   *
   * @return array
   *   An associative array of entities keyed by the state/status field.
   */
  protected function getEntities(): array {
    $entities = [];

    $collection = $this->createNode([
      'type' => 'collection',
      'field_state' => 'draft',
      'title' => $this->randomString(),
    ]);
    $collection->save();
    $entities['field_state'][] = $collection;

    $solution = $this->createNode([
      'type' => 'solution',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $collection,
    ]);
    $solution->save();
    $entities['field_state'][] = $solution;

    $release = $this->createNode([
      'type' => 'release',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $solution,
    ]);
    $release->save();
    $entities['field_state'][] = $release;

    $discussion = $this->createNode([
      'type' => 'discussion',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $collection,
    ]);
    $discussion->save();
    $entities['field_state'][] = $discussion;

    $document = $this->createNode([
      'type' => 'document',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $collection,
    ]);
    $document->save();
    $entities['field_state'][] = $document;

    $event = $this->createNode([
      'type' => 'event',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $collection,
    ]);
    $event->save();
    $entities['field_state'][] = $event;

    $news = $this->createNode([
      'type' => 'news',
      'field_state' => 'draft',
      'title' => $this->randomString(),
      'og_audience' => $collection,
    ]);
    $news->save();
    $entities['field_state'][] = $news;

    return $entities;
  }

}
