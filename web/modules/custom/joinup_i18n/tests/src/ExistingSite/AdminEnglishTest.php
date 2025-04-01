<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_i18n\ExistingSite;

use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use weitzman\LoginTrait\LoginTrait;

/**
 * @coversDefaultClass \Drupal\joinup_i18n\Plugin\LanguageNegotiation\LanguageNegotiationAdminEnglish
 *
 * @group joinup_i18n
 */
class AdminEnglishTest extends JoinupExistingSiteTestBase {

  use LoginTrait;

  /**
   * The admin.
   */
  protected AccountInterface $adminUser;

  /**
   * An authenticated user.
   */
  protected AccountInterface $authenticatedUser;

  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->createUser([], 'some_moderator', FALSE, [
      'mail' => 'some_moderator@example.com',
      'roles' => ['moderator'],
    ]);
    $this->authenticatedUser = $this->createUser([], 'some_authenticated', FALSE, [
      'mail' => 'some_authenticated@example.com',
    ]);

    $this->container->get('module_installer')
      ->install(['language_test']);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $this->container->get('module_installer')
      ->uninstall(['language_test']);

    parent::tearDown();
  }

  /**
   * Tests the actual language negotiation.
   */
  public function testActualNegotiation(): void {
    $path = 'user/' . $this->authenticatedUser->id() . '/edit';

    $this->drupalLogin($this->authenticatedUser);
    $this->drupalGet($path);
    $this->assertSession()
      ->pageTextContains('Language negotiation method: language-user');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($path);
    $this->assertSession()
      ->pageTextContains('Language negotiation method: joinup_admin_english');
  }

}
