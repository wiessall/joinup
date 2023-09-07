<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Hosting platform UI test.
 *
 * @group eu_oss_catalogue
 */
class HostingPlatformUITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'eu_oss_catalogue_test'
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $adminUser;

  /**
   * Tests page access.
   */
  public function testPageAccess(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assertSession = $this->assertSession();

    $this->drupalGet('/admin/content/eu-oss-catalogue');
    $assertSession->statusCodeEquals(403);
  }

  /**
   * Tests hosting provider operations.
   */
  public function testHostingProviderOperations(): void {
    $assertSession = $this->assertSession();

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/content/eu-oss-catalogue');
    $this->clickLink('Platform provider');

    $data = [
      'id' => 'test_hosting_platform',
      'label' => 'Test hosting platform',
      'description' => 'Description for Test hosting platform',
      'platformId' => 'github',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('hosting_platform:test_hosting_platform');
    $assertSession->pageTextContains('Test hosting platform');

    $this->clickLink('Edit');

    // Check error message.
    $data = [
      'platformSettings[projects][0][namespace]' => 'dummy-namespace-error',
      'platformSettings[projects][0][name]' => 'dummy-name-error',
    ];
    $this->submitForm($data, 'Save');
    $assertSession->pageTextContains('Namespace dummy-namespace-error doesn\'t exist on the platform');

    // Type correct namespace and name.
    $data = [
      'label' => 'Test hosting platform updated',
      'platformSettings[projects][0][namespace]' => 'dummy-namespace-1',
      'platformSettings[projects][0][name]' => 'dummy-name-1',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('Test hosting platform updated');

    // Check deletion.
    $this->clickLink('Delete');
    $assertSession->pageTextContains('This action cannot be undone.');

    $this->submitForm([], 'Delete');

    $assertSession->pageTextContains('The code hosting platform config Test hosting platform updated has been deleted.');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser(['administer eu oss catalogue']);
    $this->drupalPlaceBlock('local_actions_block');

    $settings['settings']['eu_oss_catalogue']['platform_github']['access_token'] = (object) [
      'value' => 'dummy-token',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

}
