<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_test\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup\Traits\ConfigReadOnlyTrait;
use Drupal\Tests\joinup_test\Traits\RdfEntityCreationTrait;
use Drupal\joinup\Traits\AntispamTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class for Joinup ExistingSite tests.
 */
abstract class JoinupExistingSiteTestBase extends ExistingSiteBase {

  use AntispamTrait;
  use ConfigReadOnlyTrait;
  use RdfEntityCreationTrait;

  /**
   * The list of Honeypot forms.
   *
   * @var bool[]
   */
  protected array $honeypotForms;

  /**
   * Whether the current test should run without Antibot & Honeypot features.
   *
   * @var bool
   */
  protected bool $disableSpamProtection = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    if ($this->disableSpamProtection) {
      // As ExistingSiteBase tests are running without javascript, we disable
      // Antibot & Honeypot during the tests run, if it has been requested.
      $this->removeAntispamConfigs();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    // Restores the spam protection functionality, if case.
    if ($this->disableSpamProtection) {
      $this->restoreAntispamConfigs();
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

  /**
   * Restores Honeypot settings after test run.
   */
  protected function restoreHoneypot(): void {
    static::bypassReadOnlyConfig();
    $this->container->get('config.factory')->getEditable('honeypot.settings')
      ->set('form_settings', $this->honeypotForms)
      ->save();
    static::restoreReadOnlyConfig();
  }

}
