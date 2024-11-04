<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\ExistingSite;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\Tests\joinup_test\Traits\ConfigTestTrait;
use Drupal\taxonomy\VocabularyInterface;
use weitzman\DrupalTestTraits\ExistingSiteBase;

/**
 * Base class for Joinup ExistingSite tests.
 */
abstract class JoinupExistingSiteTestBase extends ExistingSiteBase {

  /**
   * A list of users to clean from the cas mock server.
   *
   * @var array
   *   An array of usernames. Each array entry contains the 'username',
   *   'password' and 'email' keys.
   */
  protected array $casUsers = [];

  /**
   * Whether to disable the spam protection.
   */
  protected bool $disableSpamProtection = TRUE;

  use ConfigTestTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Disable the spam protection functionality.
    if ($this->disableSpamProtection) {
      $this->overrideConfig('antibot.settings', 'form_ids', NULL);
      $this->overrideConfig('honeypot.settings', 'form_settings', []);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $this->restoreOverriddenConfig();

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

    // Cleanup the cas mock server.
    \Drupal::getContainer()->get('cas_mock_server.user_manager')->deleteUsers($this->casUsers);

    parent::tearDown();

    // Delete the OG group content orphans now because parent::tearDown() is
    // destroying the container and the registered shutdown callback will fail.
    $delete_orphans_plugin->process();
  }

  /**
   * {@inheritdoc}
   */
  protected function drupalLogin(AccountInterface $account) {
    if ($this->loggedInUser) {
      $this->drupalLogout();
    }

    $this->drupalGet(Url::fromRoute('user.login'));

    // Since we are using CAS mock server, we need to override the following for
    // the difference in the form element names.
    $this->submitForm([
      'email' => $account->getEmail(),
      'password' => $account->passRaw,
    ], 'Log in');

    // @see ::drupalUserIsLoggedIn()
    $account->sessionId = $this->getSession()->getCookie(\Drupal::service('session_configuration')->getOptions(\Drupal::request())['name']);
    $this->assertTrue($this->drupalUserIsLoggedIn($account), "User {$account->getAccountName()} successfully logged in.");

    $this->loggedInUser = $account;
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * {@inheritdoc}
   */
  protected function createUser(array $permissions = [], $name = NULL, $admin = FALSE, array $values = []) {
    $account = parent::createUser($permissions, $name, $admin, $values);

    // Since we are using the CAS mock server, we need to create the user in the
    // CAS mock server and link it to the Drupal user account.
    $casUserManager = \Drupal::getContainer()->get('cas_mock_server.user_manager');
    $casUserManager->addUser([
      'username' => $account->getAccountName(),
      'email' => $account->getEmail(),
      'password' => $account->passRaw,
    ]);

    $externalAuth = \Drupal::getContainer()->get('externalauth.externalauth');
    $externalAuth->linkExistingAccount($account->getAccountName(), 'cas', $account);
    return $account;
  }

  /**
   * {@inheritdoc}
   */
  protected function createTerm(VocabularyInterface $vocabulary, array $settings = []) {
    $term = parent::createTerm($vocabulary, $settings);
    if (\Drupal::getContainer()->get('rdf_sync.mapper')->isMappedEntity($term)) {
      // Force RDF synchronization.
      \Drupal::getContainer()->get('rdf_sync.synchronizer')->destruct();
    }
    return $term;
  }

}
