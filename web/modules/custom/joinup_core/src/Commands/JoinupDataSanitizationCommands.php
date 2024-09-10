<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Consolidation\AnnotatedCommand\Hooks\HookManager;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Drush\Drupal\Commands\sql\SanitizeCommands;
use Drush\Drupal\Commands\sql\SanitizePluginInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Additional Drush sanitization plugins.
 *
 * @see \Drush\Drupal\Commands\sql\SanitizeCommands::sanitize()
 */
class JoinupDataSanitizationCommands extends DrushCommands implements SanitizePluginInterface {

  public function __construct(
    protected Connection $db,
    protected EntityFieldManagerInterface $entityFieldManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected KeyValueFactoryInterface $keyValue,
    protected FieldTypePluginManagerInterface $fieldTypePluginManager,
  ) {
    parent::__construct();
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::POST_COMMAND_HOOK, target: 'sql-sanitize')]
  public function sanitize($result, CommandData $commandData): void {
    $this->sanitizeUserEntityData();
    $this->sanitizeMessageEntityData();
    $this->sanitizeNodeEntityData();
    $this->sanitizeLogAlertsSettings();
    $this->sanitizeEmailConfirmerData();
    $this->sanitizeDownloadEventData();
  }

  /**
   * {@inheritdoc}
   */
  #[CLI\Hook(type: HookManager::ON_EVENT, target: SanitizeCommands::CONFIRMS)]
  public function messages(&$messages, InputInterface $input): void {
    $messages[] = dt('Sanitize user entity related data.');
    $messages[] = dt('Sanitize message entity related data.');
    $messages[] = dt('Sanitize node entity related data.');
    $messages[] = dt('Sanitize log alerts settings.');
    $messages[] = dt('Sanitize email confirmer data.');
    $messages[] = dt('Sanitize download event data.');
  }

  /**
   * Sanitizes user entity related data.
   */
  protected function sanitizeUserEntityData(): void {
    /* @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::storeAttributes */
    $this->truncateTable('users_data');
    $this->truncateTable('user__field_social_media');
  }

  /**
   * Sanitizes message entity related data.
   */
  protected function sanitizeMessageEntityData(): void {
    $this->sanitizeEntityFieldsData('message', [
      'field_contact_email',
      'field_contact_first_name',
      'field_contact_last_name',
      'field_message_bcc',
    ]);
  }

  /**
   * Sanitizes node entity related data.
   */
  protected function sanitizeNodeEntityData(): void {
    $this->sanitizeEntityFieldsData('node', ['field_event_contact_email']);
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

  /**
   * Sanitizes email confirmer data.
   */
  protected function sanitizeEmailConfirmerData(): void {
    $this->truncateTable('email_confirmer_confirmation');
  }

  /**
   * Sanitizes download event data.
   */
  protected function sanitizeDownloadEventData(): void {
    $this->truncateTable('joinup_download_event');
  }

  /**
   * Truncates the given table.
   *
   * @param string $tableName
   *   The table name to truncate.
   */
  protected function truncateTable(string $tableName): void {
    $this->db->truncate($tableName)->execute();
    $this->logger()->success(dt('@table_name table sanitized.', ['@table_name' => $tableName]));
  }

  /**
   * Sanitizes the corresponding entity field tables.
   *
   * @param string $entityType
   *   The entity type ID.
   * @param array $fieldNames
   *   An array of field names.
   */
  protected function sanitizeEntityFieldsData(string $entityType, array $fieldNames): void {
    $fieldStorageDefinitions = $this->entityFieldManager->getFieldStorageDefinitions($entityType);
    $fieldStorageDefinitions = array_intersect_key($fieldStorageDefinitions, array_flip($fieldNames));

    foreach ($fieldStorageDefinitions as $fieldName => $fieldStorageDefinition) {
      // Retrieve field type and ensure it's valid.
      $fieldType = $fieldStorageDefinition->getType();
      $fieldTypeClass = $this->fieldTypePluginManager->getPluginClass($fieldType);
      if (!is_subclass_of($fieldTypeClass, FieldItemInterface::class)) {
        throw new \Exception(dt('The @field_type field type is not supported.', ['@field_type' => $fieldType]));
      }

      // Generate a sample value from the field definition class.
      $fieldDefinition = BaseFieldDefinition::createFromFieldStorageDefinition($fieldStorageDefinition);
      $sampleValue = $fieldTypeClass::generateSampleValue($fieldDefinition);
      $updateFields = match ($fieldType) {
        'email',
        'telephone',
        'string',
        'string_long',
        'text',
        'text_long' => [
          $fieldName . '_value' => $sampleValue['value'],
        ],
        'text_with_summary' => [
          $fieldName . '_value' => $sampleValue['value'],
          $fieldName . '_summary' => $sampleValue['summary'],
        ],
        // Unsupported field type.
        default => throw new \Exception(dt('The @field_type field type is not supported.', ['@field_type' => $fieldType])),
      };

      // Iterate through data/revision field tables.
      $entityStorageSchemaSql = $this->keyValue->get('entity.storage_schema.sql');
      $fieldSchema = $entityStorageSchemaSql->get("$entityType.field_schema_data.$fieldName", []);
      foreach (array_keys($fieldSchema) as $fieldTable) {
        $this->db->update($fieldTable)->fields($updateFields)->execute();
        $this->logger()->success(dt('@table_name table sanitized.', ['@table_name' => $fieldTable]));
      }

      // Reset the entity cache.
      $this->entityTypeManager->getStorage($entityType)->resetCache();
    }
  }

}
