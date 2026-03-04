<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_user\Functional;

use PHPUnit\Framework\Attributes\Group;
use Drupal\Tests\cas\Traits\CasTestTrait;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;

/**
 * Tests auto-registration with admin approval on registration.
 */
#[Group('joinup_user')]
class ApprovalRegistrationTest extends JoinupExistingSiteTestBase {

  use CasTestTrait;

  const string USERNAME = 'beavis';

  /**
   * Tests the massage when user requires admin approval.
   */
  public function testApprovalMessage(): void {
    $assert = $this->assertSession();

    $email = self::USERNAME . '@example.com';
    $this->createCasUser(self::USERNAME, $email, 'needtp', [
      'firstname' => 'Beavis',
      'lastname' => 'El Gran Cornholio',
    ]
    );
    $this->casLogin($email, 'needtp');

    $assert->statusMessageContains('Thank you for applying for an account. Your account is currently pending approval by the site administrator. In the meantime, a welcome message has been sent to your email address.');
    $assert->statusMessageContains('We would like to inform you that our system automatically places accounts registered with certain generic email domains under review.');
    $assert->statusMessageContains('If your account has not been validated in the following 1 business day, please contact us through our contact form');
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    $account = user_load_by_name(self::USERNAME);
    if ($account) {
      $account->delete();
    }

    parent::tearDown();
  }

}
