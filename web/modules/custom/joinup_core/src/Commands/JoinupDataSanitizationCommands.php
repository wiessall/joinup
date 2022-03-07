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
  protected $db;

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
    $this->sanitizeAuthMapTable();
  }

  /**
   * {@inheritdoc}
   *
   * @hook on-event sql-sanitize-confirms
   */
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize user social media data.');
    $messages[] = dt('Sanitize EU Login account mappings.');
  }

  /**
   * Sanitizes the user social media data.
   */
  protected function sanitizeUserSocialMedia(): void {
    $this->db->truncate('user__field_social_media')->execute();
    $this->logger()->success(dt('user__field_social_media table sanitized.'));
  }

  /**
   * Sanitizes the {authmap} table.
   */
  protected function sanitizeAuthMapTable(): void {
    $this->db->update('authmap')
      ->expression('authname', 'CONCAT(SUBSTRING(authname, 1, 1), LEFT(SHA1(UUID()), 7))')
      ->condition('uid', 1, '<>')
      ->execute();
    $this->logger()->success(dt('authmap table sanitized.'));
  }

}
