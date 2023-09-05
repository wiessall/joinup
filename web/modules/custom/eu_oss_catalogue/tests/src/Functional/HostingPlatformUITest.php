<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Hosting platform UI test.
 *
 * @group eu_oss_catalogue
 */
class HostingPlatformUITest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['eu_oss_catalogue'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests page access.
   */
  public function testPageAccess(): void {
    $this->drupalLogin($this->drupalCreateUser());

    $assert_session = $this->assertSession();

    $this->drupalGet('/admin/content/eu-oss-catalogue');
    $assert_session->statusCodeEquals(403);

    $role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $this->grantPermissions($role, ['administer eu oss catalogue']);
    $this->getSession()->reload();

    $assert_session->statusCodeEquals(200);
  }

}

