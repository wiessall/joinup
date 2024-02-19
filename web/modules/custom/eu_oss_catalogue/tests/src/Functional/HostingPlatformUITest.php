<?php

declare(strict_types=1);

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
    'editor',
    'eu_oss_catalogue_test',
    'markdown_easy',
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
   * Tests hosting provider description.
   */
  public function testDescription(): void {
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('admin/content/eu-oss-catalogue');
    $assertSession = $this->assertSession();

    // Description for github_example.
    $assertSession->pageTextContains('Code hosting platform');
    // Description for github_mockup.
    $assertSession->pageTextContains('Test GitHub hosting provider');
    // Platform label for github_example and github_mockup.
    $assertSession->pageTextContains('GitLab');
  }

}
