<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

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
    $assert_session->fieldExists('Solution');
    $assert_session->fieldExists('Source');
    $assert_session->fieldExists('Language');
    $assert_session->fieldExists('Published');
    $assert_session->buttonExists('Filter');
  }

}
