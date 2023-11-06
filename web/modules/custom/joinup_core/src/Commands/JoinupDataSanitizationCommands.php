<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Drupal\Core\Database\Connection;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Additional Drush sanitization plugins.
 *
 * @see \Drush\Drupal\Commands\sql\SanitizeCommands::sanitize()
 */
class JoinupDataSanitizationCommands extends DrushCommands implements SanitizePluginInterface {

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.
   */
  public function __construct(protected Connection $db) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   *
   * @hook post-command sql:sanitize
   */
  public function sanitize($result, CommandData $commandData): void {
    $this->sanitizeUserSocialMedia();
    $this->sanitizeLogAlertsSettings();
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize user social media data.');
    $messages[] = dt('Sanitize log alerts settings.');
  }

  /**
   * Sanitizes the user social media data.
   */
  protected function sanitizeUserSocialMedia(): void {
    $this->db->truncate('user__field_social_media')->execute();
    $this->logger()->success(dt('user__field_social_media table sanitized.'));
  }

  /**
   * Sanitizes the log alerts settings.
   */
  protected function sanitizeLogAlertsSettings(): void {
    $this->db->delete('key_value')
      ->condition('collection', 'state')
      ->condition('name', 'joinup.log_alerts')
      ->execute();
    $this->logger()->success(dt('joinup.log_alerts state var sanitized.'));
  }

}
