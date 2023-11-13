<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\InstallStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the OSS solution listing page.
 *
 * @group eu_oss_catalogue
 */
class OssSolutionListingPageTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'dblog',
    'eu_oss_catalogue_test',
    'language',
    'views',
    'views_bulk_operations',
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

    // The admin view requires the 'view_mode_tile' view mode so we are creating
    // it here.
    $view_mode_storage = $this->container->get('entity_type.manager')->getStorage('entity_view_mode');
    $view_mode = $view_mode_storage->create([
      'targetEntityType' => 'node',
      'id' => 'node.view_mode_tile',
      'label' => 'Tile',
    ]);
    $view_mode->save();

    // Re-try to install the optional config for the eu_oss_catalogue module.
    // Get path for the eu_oss_catalogue module.
    $base_path = \Drupal::getContainer()->get('module_handler')->getModule('eu_oss_catalogue')->getPath();

    $optional_install_path = $base_path . '/' . InstallStorage::CONFIG_OPTIONAL_DIRECTORY;
    if (is_dir($optional_install_path)) {
      // Install any optional config the module provides.
      $storage = new FileStorage($optional_install_path, StorageInterface::DEFAULT_COLLECTION);
      \Drupal::getContainer()->get('config.installer')->installOptionalConfig($storage, ['module' => 'eu_oss_catalogue']);
    }

    \Drupal::getContainer()->get('router.builder')->rebuild();
  }

  /**
   * Tests the public page (/oss-solutions).
   */
  public function testPublicOssSolutionPage(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    // Check the view exists, is access-restricted, and some defaults are there.
    $this->drupalGet('/oss-solutions');

    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Open Source Software Solutions | Drupal');
    $assert_session->fieldExists('Source');
    $assert_session->fieldExists('Category');
    $assert_session->fieldExists('License');
    $assert_session->buttonExists('Filter');
  }

  /**
   * Tests the administrative page.
   *
   * It tests the following page /admin/content/eu-oss-catalogue/oss-solutions.
   */
  public function testAdministrativeOssSolutionPage(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    $this->drupalGet('/admin/content/eu-oss-catalogue/oss-solutions');
    $assert_session->statusCodeEquals(403);

    $role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->grantPermissions($role, ['administer eu oss catalogue']);
    $this->getSession()->reload();

    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    // Check the view exists, is access-restricted, and some defaults are there.
    $this->drupalGet('/admin/content/eu-oss-catalogue/oss-solutions');

    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Open Source Software Solutions | Drupal');
    $assert_session->fieldExists('OSS Solution');
    $assert_session->fieldExists('Source Catalogue');
    $assert_session->fieldExists('Language');
    $assert_session->fieldExists('Published');
    $assert_session->buttonExists('Filter');
  }

}
