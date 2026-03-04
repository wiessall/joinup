<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use PHPUnit\Framework\Attributes\Group;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\UserInterface;

/**
 * Hosting platform UI test.
 */
#[Group('eu_oss_catalogue')]
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
      'weblinks[0][url]' => 'https://code.europa.eu',
      'platformId' => 'github',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('hosting_platform:test_hosting_platform');
    $assertSession->pageTextContains('Test hosting platform');

    $this->clickTableRowAction('hosting_platform:test_hosting_platform', 'Edit');

    // Type correct namespace and name.
    $data = [
      'label' => 'Test hosting platform updated',
      'platformSettings[projects][0][namespace]' => 'dummy-namespace-1',
      'platformSettings[projects][0][name]' => 'dummy-name-1',
    ];
    $this->submitForm($data, 'Save');

    $assertSession->pageTextContains('Test hosting platform updated');

    // Check deletion.
    $this->clickTableRowAction('hosting_platform:test_hosting_platform', 'Delete');
    $assertSession->pageTextContains('This action cannot be undone.');

    $this->submitForm([], 'Delete');

    $assertSession->pageTextContains('The code hosting platform config Test hosting platform updated has been deleted.');
  }

  /**
   * Tests visibility of the URL field.
   */
  public function testVisibilityOfUrlField(): void {
    $this->drupalLogin($this->adminUser);

    $this->drupalGet('admin/content/eu-oss-catalogue/hosting-platform/add', ['query' => ['platformId' => 'gitlab_self_hosted']]);
    $this->assertSession()->fieldEnabled('platformSettings[baseUrl]');

    $data = [
      'label' => 'Test hosting platform',
      'id' => 'test_hosting_platform',
      'description' => 'Description for Test hosting platform',
      'type' => 'municipal',
      'country' => 'AO',
      'weblinks[0][url]' => 'https://test-hosting-platform.com',
      'platformSettings[baseUrl]' => 'https://github.self-hosted.com',
      'platformSettings[projects][0][namespace]' => 'dummy-namespace-1',
      'platformSettings[projects][0][name]' => 'dummy-name-1',
    ];
    $this->submitForm($data, 'Save');

    $this->drupalGet('admin/content/eu-oss-catalogue/hosting-platform/manage/test_hosting_platform');
    $this->assertSession()->fieldDisabled('platformSettings[baseUrl]');
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

  /**
   * Clicks a specific action link inside a table row.
   *
   * @param string $rowText
   *   The unique text to locate the correct row.
   * @param string $action
   *   The action to perform ('Edit' or 'Delete').
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function clickTableRowAction(string $rowText, string $action): void {
    $xpath = "//tr[td//text()[contains(., '{$rowText}')]]//a[normalize-space(text())='{$action}']";
    $link = $this->getSession()->getPage()->find('xpath', $xpath);

    if (!$link) {
      throw new ElementNotFoundException($this->getSession()
        ->getDriver(), 'link', 'xpath', $xpath);
    }

    $link->click();
  }

}
