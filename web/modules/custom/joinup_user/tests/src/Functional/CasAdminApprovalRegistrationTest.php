<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_user\Functional;

use Drupal\Core\Site\Settings;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\Tests\cas\Functional\CasBrowserTestBase;
use Drupal\Tests\cas\Traits\CasTestTrait;
use Drupal\user\UserInterface;

/**
 * Tests auto-registration with admin approval on registration.
 *
 * @group teste
 */
class CasAdminApprovalRegistrationTest extends CasBrowserTestBase {

  use CasTestTrait;
  use AssertMailTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'cas_mock_server',
  ];

  /**
   * Setup function for the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Retrieve the existing settings object.
    $original_settings = \Drupal::getContainer()->get('settings');
    // Copy and update the locale custom strings.
    $custom_strings_en = [];
    $custom_strings_en['locale_custom_strings_en'][''] = ['Thank you for applying for an account. Your account is currently pending approval by the site administrator.<br />In the meantime, a welcome message with further instructions has been sent to your email address.' => 'Thank you for applying for an account. Your account is currently pending approval by the site administrator. In the meantime, a welcome message has been sent to your email address. We would like to inform you that our system automatically places accounts registered with certain generic email domains under review. This measure helps us maintain the integrity of the platform by minimising the risk of spam or misuse. If your account has not been validated in the following 3 business days, please contact us through our <a href="/contact">contact form</a>.'];
    // Create a new settings object with updated locale strings.
    $new_settings = new Settings(array_merge($original_settings->getAll(), $custom_strings_en));
    // Override the settings in the container.
    \Drupal::getContainer()->set('settings', $new_settings);
  }

  /**
   * Tests the case when Drupal requires admin approval on registration.
   */
  public function testAdminApproval(): void {
    $assert = $this->assertSession();

    $this->config('cas.settings')
      ->set('user_accounts.auto_register', TRUE)
      ->set('user_accounts.email_assignment_strategy', 1)
      ->set('user_accounts.email_attribute', 'email')
      ->save();
    $this->config('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)
      ->save();
    $this->createCasUser('beavis', 'beavis@example.com', 'needtp', [
      'firstname' => 'Beavis',
      'lastname' => 'El Gran Cornholio',
    ]);
    // Place the login/logout block so that we can check if user is logged in.
    $this->placeBlock('system_menu_block:account');

    // Account registration policy is not yet enforced.
    $this->casLogin('beavis@example.com', 'needtp');
    $this->assertUserLoggedIn();
    $account = user_load_by_name('beavis');
    $this->assertInstanceOf(UserInterface::class, $account);
    $this->assertTrue($account->isActive());

    // Delete the local account and enforce site's registration policy.
    $this->drupalLogout();
    $account->delete();
    $this->config('cas.settings')
      ->set('user_accounts.auto_register_follow_registration_policy', TRUE)
      ->save();

    // Account is created and pending approval.
    $this->casLogin('beavis@example.com', 'needtp');
    $this->assertUserNotLoggedIn();
    $assert->statusMessageContains('Thank you for applying for an account. Your account is currently pending approval by the site administrator.');
    $assert->statusMessageContains('In the meantime, a welcome message with further instructions has been sent to your email address.');
    $account = user_load_by_name('beavis');
    $this->assertInstanceOf(UserInterface::class, $account);
    $this->assertTrue($account->isBlocked());
    [$email] = $this->getMails(['to' => 'beavis@example.com']);
    $this->assertSame('Account details for beavis at Drupal (pending admin approval)', $email['subject']);
    $this->assertStringContainsString('Thank you for registering at Drupal. Your application for an account is', $email['body']);
    $this->assertStringContainsString('currently pending approval', $email['body']);
    [$email] = $this->getMails(['to' => 'simpletest@example.com']);
    $this->assertSame('Account details for beavis at Drupal (pending admin approval)', $email['subject']);
    $this->assertStringContainsString('beavis has applied for an account.', $email['body']);

    // The user comes back and tries again to login. The account is registered
    // but still blocked. Check that it gets the proper error message.
    $this->casLogin('beavis@example.com', 'needtp');
    $this->assertUserNotLoggedIn();
    $assert->statusMessageContains('Your account is blocked or has not been activated. Please contact a site administrator.', 'error');

    // Delete the local account and drop admin approval.
    $account->delete();
    $this->config('user.settings')
      ->set('register', UserInterface::REGISTER_VISITORS)
      ->save();

    // Account is created and the user is logged in.
    $this->casLogin('beavis@example.com', 'needtp');
    $this->assertUserLoggedIn();
    $account = user_load_by_name('beavis');
    $this->assertInstanceOf(UserInterface::class, $account);
    $this->assertTrue($account->isActive());
  }

}
