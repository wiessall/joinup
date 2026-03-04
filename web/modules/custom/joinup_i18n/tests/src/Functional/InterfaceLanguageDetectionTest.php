<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_i18n\ExistingSite;

use Drupal\Tests\BrowserTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Interface text language detection.
 */
#[Group('joinup_i18n')]
class InterfaceLanguageDetectionTest extends BrowserTestBase {

  /**
   * A user with permission to access admin pages and administer languages.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * A user with permission to Bypass English.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $translatorUser;

  /**
   * A non-administrator user for this test.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $regularUser;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
    'language',
    'language_test',
    'user_language_test',
    'joinup_i18n',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser([
      'administer users',
      'administer languages',
      'access administration pages',
      'bypass enforced english',
    ]);

    $this->translatorUser = $this->drupalCreateUser([
      'administer users',
      'bypass enforced english',
      'view admin pages in preferred language',
    ]);

    $this->regularUser = $this->drupalCreateUser();

    // Set the language negotiation settings directly.
    $this->config('language.types')
      ->set('negotiation.language_interface.enabled', [
        'joinup_force_english' => 1,
        'joinup_admin_english' => 2,
        'language-url' => 3,
        'language-user' => 4,
        'language-selected' => 5,
      ])
      ->set('negotiation.language_interface.method_weights', [
        'joinup_force_english' => 1,
        'joinup_admin_english' => 2,
        'language-url' => 3,
        'language-user' => 4,
        'language-selected' => 5,
      ])
      ->save();

    // Enable one language.
    ConfigurableLanguage::createFromLangcode('it')->save();
  }

  /**
   * Tests the actual language negotiation.
   */
  public function testActualNegotiation(): void {
    $path = '/it/user/' . $this->regularUser->id() . '/edit';

    $this->drupalGet('/it/');
    $this->assertSession()
      ->pageTextContains('Language negotiation method: joinup_force_english');

    $this->drupalLogin($this->regularUser);
    $this->drupalGet($path);
    $this->assertSession()
      ->pageTextContains('Language negotiation method: joinup_force_english');

    $this->drupalLogin($this->adminUser);
    $this->drupalGet($path);
    $this->assertSession()
      ->pageTextContains('Language negotiation method: joinup_admin_english');

    $this->drupalLogin($this->translatorUser);
    $this->drupalGet($path);
    $this->assertSession()
      ->pageTextContains('Language negotiation method: language-url');
  }

}
