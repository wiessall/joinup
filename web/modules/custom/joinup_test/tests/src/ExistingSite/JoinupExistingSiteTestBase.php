<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_test\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\joinup_test\Traits\RdfEntityCreationTrait;
use Drupal\joinup\Traits\ConfigReadOnlyTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class for Joinup ExistingSite tests.
 */
abstract class JoinupExistingSiteTestBase extends ExistingSiteBase {

  use ConfigReadOnlyTrait;
  use RdfEntityCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable the spam protection functionality.
    \Drupal::state()->set('joinup_core.antispam.enabled', FALSE);
    // Clear the cache bin storing cached markup.
    foreach (['page', 'dynamic_page_cache', 'render'] as $bin) {
      \Drupal::cache($bin)->deleteAll();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    // Restores the spam protection functionality.
    \Drupal::state()->delete('joinup_core.antispam.enabled');
    // Clear the cache bin storing cached markup.
    foreach (['page', 'dynamic_page_cache', 'render'] as $bin) {
      \Drupal::cache($bin)->deleteAll();
    }

    // Avoid sending notifications during test entities cleanup. First, sort the
    // entities by moving the 'user' entities at the end, as deleting a user
    // might trigger deletion of entities authored by that user. Such entities
    // are subsequently reloaded, losing the 'skip_notification' flag.
    // @see node_user_predelete()
    usort($this->cleanupEntities, function (EntityInterface $left, EntityInterface $right): int {
      if ($left->getEntityTypeId() !== 'user' && $right->getEntityTypeId() === 'user') {
        return -1;
      }
      elseif ($left->getEntityTypeId() === 'user' && $right->getEntityTypeId() !== 'user') {
        return 1;
      }
      return 0;
    });
    foreach ($this->cleanupEntities as $entity) {
      $entity->skip_notification = TRUE;
    }

    /** @var \Drupal\Component\Plugin\PluginManagerInterface $delete_orphans_manager */
    $delete_orphans_manager = $this->container->get('plugin.manager.og.delete_orphans');
    /** @var \Drupal\og\OgDeleteOrphansInterface $delete_orphans_plugin */
    $delete_orphans_plugin = $delete_orphans_manager->createInstance('simple');

    // The parent method might cleanup config entities.
    $this->bypassReadOnlyConfig();
    parent::tearDown();
    $this->restoreReadOnlyConfig();

    // Delete the OG group content orphans now because parent::tearDown() is
    // destroying the container and the registered shutdown callback will fail.
    $delete_orphans_plugin->process();
  }

}
