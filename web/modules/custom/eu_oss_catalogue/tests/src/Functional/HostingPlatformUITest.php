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
    'eu_oss_catalogue_test',
    'multivalue_form_element',
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
   * Tests hosting provider creation.
   */
  public function testCreate(): void {
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

    $this->drupalGet('admin/content/eu-oss-catalogue/hosting-platform/manage/test_hosting_platform');
    $data = [
      'platformSettings[projects][0][namespace]' => 'dummy-namespace',
      'platformSettings[projects][0][name]' => 'dummy-name',
    ];
    $this->submitForm($data, 'Save');
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

