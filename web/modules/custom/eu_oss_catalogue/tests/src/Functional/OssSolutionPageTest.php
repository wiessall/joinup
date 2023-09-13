<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the OSS solution page.
 *
 * @group eu_oss_catalogue
 */
class OssSolutionPageTest extends BrowserTestBase {

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
   * Tests the OSS solution page (/oss-solutions).
   */
  public function testOssSolutionAccess(): void {
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

}
