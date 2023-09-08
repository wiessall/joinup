<?php

declare(strict_types = 1);

namespace Drupal\Tests\joinup_core\Functional\Mail;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\joinup_core\Traits\ConfigInstallTestTrait;
use Drupal\Tests\joinup_core\Traits\DefaultThemeWithDependeciesSetupTrait;
use Drupal\Tests\joinup_core\Traits\MailTestTrait;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\joinup_test_time\Datetime\TestTime;
use Drupal\user\Entity\User;

/**
 * Base test class for email tests.
 */
abstract class JoinupMailFunctionalTestBase extends BrowserTestBase {

  use ConfigInstallTestTrait;
  use DefaultThemeWithDependeciesSetupTrait;
  use MailTestTrait;
  use SparqlConnectionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $configSchemaCheckerExclusions = [
    'joinup_core.outdated_content_threshold',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'ventuno';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'allowed_formats',
    'cas',
    'cas_mock_server',
    'collection',
    'embed_block',
    'field',
    'filter',
    'interoperable_europe',
    'joinup_collection',
    'joinup_core',
    'joinup_menu',
    'joinup_notification',
    'joinup_test_time',
    'joinup_video',
    'joinup_workflow',
    'layout_builder',
    'linkit',
    'message',
    'message_digest',
    'message_notify',
    'monolog',
    'og',
    'options',
    'pathauto',
    'state_machine_revisions',
    'swiftmailer',
    'text',
    'topic',
    'user',
    'workflow_state_permission',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->setUpSparql();

    // UID 1's email address.
    User::load(1)
      ->setUsername('admin')
      ->setEmail('admin@example.com')
      ->save();

    $this->installFromConfigCollection([
      'system.mail',
      'system.site',
      'mailsystem.settings',
      'swiftmailer.transport',
    ]);

    $this->config('system.theme')->set('default', 'ventuno')->save();
    $this->config('system.site')
      ->set('mail', 'joinup@example.com')
      ->save();

    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    // Set SwiftMailer's transport type to 'null' transport.
    $this->config('swiftmailer.transport')
      ->set('transport', SWIFTMAILER_TRANSPORT_NULL)
      ->save();

    $this->config('mailsystem.settings')
      ->set('defaults.sender', 'test_mail_collector')
      ->save();

    // Set a mocked time to stabilize e.g. the generated password reset link.
    \Drupal::state()->set(TestTime::MOCK_TIMESTAMP_STATE_KEY, 1690449287);
  }

}
