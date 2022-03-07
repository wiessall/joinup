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
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $db;

  /**
   * Constructs a new Drush commands class instance.
   *
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.
   */
  public function __construct(Connection $db) {
    parent::__construct();
    $this->db = $db;
  }

  /**
   * {@inheritdoc}
   *
   * @hook post-command sql:sanitize
   */
  public function sanitize($result, CommandData $commandData): void {
    $this->sanitizeUserSocialMedia();
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize user social media data.');
  }

  /**
   * Sanitizes the user social media data.
   */
  protected function sanitizeUserSocialMedia(): void {
    $this->db->truncate('user__field_social_media')->execute();
    $this->logger()->success(dt('user__field_social_media table sanitized.'));
  }

}
