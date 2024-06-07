<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Kernel\Mail;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\joinup_core\Traits\ConfigInstallTestTrait;
use Drupal\Tests\joinup_core\Traits\MailTestTrait;
use Drupal\Tests\joinup_core\Traits\ModuleSetupTestTrait;
use Drupal\Tests\sparql_entity_storage\Traits\SparqlConnectionTrait;
use Drupal\joinup_test_time\Datetime\TestTime;
use Drupal\user\Entity\User;

/**
 * Base class for kernel tests testing sent emails.
 */
abstract class JoinupMailKernelTestBase extends KernelTestBase {

  use ConfigInstallTestTrait;
  use ModuleSetupTestTrait;
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
  protected static $modules = [
    'filter',
    'joinup_core',
    'joinup_test_time',
    'system',
    'user',
    'token',
    'mailsystem',
    'symfony_mailer_lite',

    // Joinup Notification + its dependencies.
    'joinup_notification',
    'field',
    'options',
    'og',
    'message',
    'message_notify',
    'joinup_workflow',
    'state_machine_revisions',

    // Ventuno theme dependencies.
    'interoperable_europe',
    'joinup_menu',
    'joinup_collection',
    'rdf_entity',
    'sparql_entity_storage',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->setUpSparql();

    $this->fullyInstallModuleIfExists([
      'tecla',
      'rdf_entity',
      'sparql_entity_storage',
      'user',
      'system',
      'filter',
      'joinup_core',
      'joinup_notification',
      'og',
      'message',
      'message_notify',
      'joinup_workflow',
      'state_machine_revisions',
      'rdf_entity',
      'sparql_entity_storage',
      'mailsystem',
      'symfony_mailer_lite',
    ]);

    \Drupal::service('theme_installer')
      ->install(['stark', 'oe_bootstrap_theme', 'ventuno']);

    $this->installFromConfigCollection([
      'system.mail',
      'system.site',
      'mailsystem.settings',
      'symfony_mailer_lite.settings',
      'symfony_mailer_lite.symfony_mailer_lite_transport.native',
      'symfony_mailer_lite.symfony_mailer_lite_transport.null',
      'symfony_mailer_lite.symfony_mailer_lite_transport.sendmail',
      'field.storage.user.field_user_first_name',
      'field.storage.user.field_user_family_name',
      'field.field.user.user.field_user_first_name',
      'field.field.user.user.field_user_family_name',
    ]);

    $this->config('system.theme')->set('default', 'ventuno')->save();
    $this->config('system.site')
      ->set('mail', 'joinup@example.com')
      ->save();
    $this->config('system.mail')
      ->set('interface.default', 'test_mail_collector')
      ->save();

    $this->config('mailsystem.settings')
      ->set('defaults.sender', 'test_mail_collector')
      ->save();

    // UID 1's email address.
    User::load(1)
      ->setUsername('admin')
      ->setEmail('admin@example.com')
      ->set('field_user_first_name', 'AdminJane')
      ->set('field_user_family_name', 'AdminDoe')
      ->save();

    // Set a mocked time to stabilize e.g. the generated password reset link.
    \Drupal::state()->set(TestTime::MOCK_TIMESTAMP_STATE_KEY, 1690449287);
  }

}
