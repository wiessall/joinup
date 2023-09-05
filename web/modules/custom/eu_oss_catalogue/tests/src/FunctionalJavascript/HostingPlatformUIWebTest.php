<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;

/**
 * Hosting platform UI test.
 *
 * @group eu_oss_catalogue
 */
class HostingPlatformUIWebTest extends WebDriverTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'eu_oss_catalogue',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests creation of hosting platform.
   */
  public function testCreationOfHostingPlatform(): void {
    $assertSession = $this->assertSession();

    $this->drupalGet('admin/content/eu-oss-catalogue/hosting-platform/add');
    $assertSession->pageTextContains('Add configuration');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $user = $this->drupalCreateUser(['administer eu oss catalogue']);
    $this->drupalLogin($user);
  }

}

