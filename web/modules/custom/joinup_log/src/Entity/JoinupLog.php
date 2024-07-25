<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\user\EntityOwnerTrait;
use Drupal\user\UserInterface;

/**
 * Defines the joinup log entity class.
 *
 * @ContentEntityType(
 *   id = "joinup_log",
 *   label = @Translation("Joinup log"),
 *   label_collection = @Translation("Joinup logs"),
 *   label_singular = @Translation("joinup log"),
 *   label_plural = @Translation("joinup logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count joinup log",
 *     plural = "@count joinup logs",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\joinup_log\JoinupLogStorage",
 *     "storage_schema" = "Drupal\joinup_log\JoinupLogStorageSchema",
 *     "views_data" = "Drupal\joinup_log\JoinupLogViewsData",
 *   },
 *   base_table = "joinup_log",
 *   admin_permission = "access site reports",
 *   entity_keys = {
 *     "id" = "id",
 *     "owner" = "uid",
 *   },
 * )
 */
final class JoinupLog extends ContentEntityBase implements JoinupLogInterface {

  use StringTranslationTrait, EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']->setRequired(TRUE)
      ->setDescription(t('The user that triggered the logging'));

    $fields['actor'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Actor'))
      ->setDescription(t('The user which is the subject of the event'))
      ->setRequired(FALSE)
      ->setTranslatable(FALSE)
      ->setSetting('target_type', 'user');

    $fields['group'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Group'))
      ->setDescription(t('RDF entity this log entry relates to, if any.'))
      ->setTargetEntityTypeId('rdf_entity')
      ->setRequired(FALSE)
      ->setSetting('target_type', 'rdf_entity');

    $fields['type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setDescription(new TranslatableMarkup('Type of log message'))
      ->setSettings([
        'allowed_values_function' => self::class . '::allowedTypes',
      ]);

    $fields['message'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Message'))
      ->setDescription(new TranslatableMarkup('Text of log message to be passed into the t() function.'))
      ->setRequired(TRUE);

    $fields['severity'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(new TranslatableMarkup('Severity'))
      ->setDescription(new TranslatableMarkup('The severity level of the event; ranges from 0 (Emergency) to 7 (Debug)'))
      ->setSetting('unsigned', TRUE)
      ->setSetting('size', 'tiny')
      ->setSettings([
        'allowed_values' => RfcLogLevel::getLevels(),
      ])
      ->setRequired(TRUE);

    $fields['location'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Location'))
      ->setDescription(new TranslatableMarkup('URL of the origin of the event.'))
      ->setSetting('max_length', 2048);

    $fields['referer'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Referer'))
      ->setDescription(new TranslatableMarkup('URL of referring page.'))
      ->setSetting('max_length', 2048);

    $fields['hostname'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Hostname'))
      ->setDescription(new TranslatableMarkup('Hostname of the user who triggered the event.'));

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Timestamp'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of when event occurred.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): ?GroupInterface {
    return $this->get('group')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getSeverity(): int {
    return (int) $this->get('severity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocation(): ?string {
    return $this->get('location')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferer(): ?string {
    return $this->get('referer')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostname(): ?string {
    return $this->get('hostname')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp(): int {
    return (int) $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getActor(): ?UserInterface {
    return $this->get('actor')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getActorId(): ?int {
    return $this->get('actor')->isEmpty() ? NULL : (int) $this->get('actor')
      ->getString();
  }

  /**
   * Returns the allowed types.
   *
   * @return array
   *   The allowed types.
   */
  public static function allowedTypes(): array {
    return [
      'joinup:add_member' => new TranslatableMarkup('Add member'),
      'joinup:invite_member' => new TranslatableMarkup('Invite Member'),
      'joinup:add_announcement' => new TranslatableMarkup('Add announcement'),
      'joinup:add_custom_page' => new TranslatableMarkup('Add custom page'),
      'joinup:add_solution' => new TranslatableMarkup('Add solution'),
      'joinup:add_news' => new TranslatableMarkup('Add news'),
      'joinup:add_discussion' => new TranslatableMarkup('Add discussion'),
      'joinup:add_document' => new TranslatableMarkup('Add document'),
      'joinup:add_event' => new TranslatableMarkup('Add event'),
      'joinup:approve_the_pending_membership' => new TranslatableMarkup('Approve the pending membership'),
      'joinup:block_membership' => new TranslatableMarkup('Block membership'),
      'joinup:unblock_membership' => new TranslatableMarkup('Unblock membership'),
      'joinup:deleted_membership' => new TranslatableMarkup('Deleted membership'),
      'joinup:add_the_author_role_to_member' => new TranslatableMarkup('Add the author role to member'),
      'joinup:add_the_facilitator_role_to_member' => new TranslatableMarkup('Add the facilitator role to member'),
      'joinup:transfer_the_ownership_of_the_collection_to_member' => new TranslatableMarkup('Transfer the ownership of the collection to member'),
      'joinup:remove_the_facilitator_role_from_member' => new TranslatableMarkup('Remove the facilitator role from member'),
      'joinup:remove_the_author_role_from_member' => new TranslatableMarkup('Remove the author role from member'),
    ];
  }

}
