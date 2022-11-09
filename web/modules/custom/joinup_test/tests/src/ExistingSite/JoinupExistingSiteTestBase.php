<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_test\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\joinup_test\Traits\RdfEntityCreationTrait;
use Drupal\joinup\Traits\AntibotTrait;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class for Joinup ExistingSite tests.
 */
abstract class JoinupExistingSiteTestBase extends ExistingSiteBase {

  use AntibotTrait;
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
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // A user whose account in not yet linked with an EU Login account has
    // limited access to the website features. They can login only by using the
    // one-time-login mechanism, but one-time-login is meant only to allow
    // password recovery. On the other hand, in testing, we use the
    // one-time-login mechanism to perform the authentication, instead of
    // following the CAS login process, thus we disable limited access. Tests
    // that are specifically testing limited access are able to use this
    // kill-switch by temporary re-enabling the functionality during testing.
    // @see \Drupal\Tests\joinup_eulogin\ExistingSite\JoinupEuLoginTest::testLimitedAccess()
    $this->state = $this->container->get('state');
    $this->state->set('joinup_eulogin.disable_limited_access', TRUE);

    if ($this->disableSpamProtection) {
      // As ExistingSiteBase tests are running without javascript, we disable
      // Antibot & Honeypot during the tests run, if it has been requested.
      $this->disableAntibot();
      $this->disableHoneypot();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    // Restores the spam protection functionality, if case.
    if ($this->disableSpamProtection) {
      $this->restoreAntibot();
      $this->restoreHoneypot();
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

    // Re-enable limited access functionality.
    $this->state->delete('joinup_eulogin.disable_limited_access');

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
   * Disables Honeypot settings during the test run.
   */
  protected function disableHoneypot(): void {
    static::bypassReadOnlyConfig();
    $config_factory = $this->container->get('config.factory');
    $config = $config_factory->getEditable('honeypot.settings');
    if (!isset($this->honeypotForms)) {
      $this->honeypotForms = $config->get('form_settings') ?? [];
    }
    $config
      ->set('form_settings', array_fill_keys(array_keys($this->honeypotForms), FALSE))
      ->save();
    static::restoreReadOnlyConfig();
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
