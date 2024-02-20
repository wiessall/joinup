<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\FunctionalJavascript;

use Behat\Mink\Element\DocumentElement;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\user\UserInterface;

/**
 * Platform providers UI test.
 *
 * @group eu_oss_catalogue
 */
class PlatformProvidersTest extends WebDriverTestBase {

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
   * Tests hosting provider operations.
   */
  public function testHostingProviderOperations(): void {
    $assertSession = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/content/eu-oss-catalogue');

    $this->clickLink('Platform provider');

    $page->selectFieldOption('Code hosting platform', 'GitLab (self hosted)');
    $page->waitFor(10, function (DocumentElement $page) {
      return $page->hasField('URL');
    });

    $data = [
      'label' => 'Test hosting platform',
      'description' => 'Description for Test hosting platform',
      'platformSettings[baseUrl]' => 'https://github.self-hosted.com',
      'platformSettings[projects][0][namespace]' => 'dummy-namespace-1',
      'platformSettings[projects][0][name]' => 'dummy-name-1',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('hosting_platform:test_hosting_platform');
    $assertSession->pageTextContains('Test hosting platform');

    $this->clickLink('Edit');

    $assertSession->fieldEnabled('platformSettings[baseUrl]');

    // Perform updates.
    $data = [
      'label' => 'Test hosting platform updated',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('Test hosting platform updated');

    // Check deletion.
    $page->find('css', '.dropbutton-toggle button')->click();
    $this->clickLink('Delete');
    $assertSession->pageTextContains('This action cannot be undone.');

    $this->submitForm([], 'Delete');

    $assertSession->pageTextContains('The code hosting platform config Test hosting platform updated has been deleted.');
  }

}
