<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_distribution\DownloadEventStorageSchema;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Drupal\views\EntityViewsData;

/**
 * Provides a backend for asset distribution download event logging.
*/
#[ContentEntityType(
  id: 'download_event',
  label: new TranslatableMarkup('Download event'),
  handlers: [
    'storage_schema' => DownloadEventStorageSchema::class,
    'views_data' => EntityViewsData::class,
  ],
  base_table: 'joinup_download_event',
  translatable: FALSE,
  entity_keys: [
    'id' => 'id',
    'uid' => 'uid',
  ],
  constraints: [
    'DownloadEvent' => [],
  ],
)]
class DownloadEvent extends ContentEntityBase implements EntityOwnerInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('User'))
      ->setDescription(t('The user that created the event.'))
      ->setSetting('target_type', 'user');

    $fields['mail'] = BaseFieldDefinition::create('email')
      ->setLabel(t('Email'))
      ->setDescription(t('The email of the anonymous or authenticated user.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time that the entry was created.'));

    $fields['file'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Distribution file'))
      ->setDescription(t('The downloaded asset distribution file.'))
      ->setSetting('target_type', 'file')
      ->setRequired(TRUE);

    $fields['parent_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent entity type'))
      ->setDescription(t('The solution or release that the distribution belongs to.'))
      ->setRequired(FALSE);

    $fields['parent_entity_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Parent entity ID'))
      ->setDescription(t('The entity ID of the solution or release that the distribution belongs to.'))
      ->setRequired(FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    $account = $this->getOwner();
    $name = $account ? $account->label() : $this->mail->value;
    $time = \Drupal::service('date.formatter')->format($this->created->value);
    $file = $this->file->entity->label();

    // Provide a label just for administrative purposes.
    return "$file downloaded by $name at $time";
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    /** @var \Drupal\user\UserInterface $account */
    $account = $this->uid->entity;
    if ($account && !$account->isAnonymous()) {
      // If the user is not anonymous set its E-mail in 'mail' field.
      $this->set('mail', $account->getEmail());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getOwnerId(): int {
    return (int) $this->getEntityKey('uid');
  }

  /**
   * {@inheritdoc}
   */
  public function getOwner(): UserInterface {
    return $this->get('uid')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwnerId($uid): static {
    $this->set('uid', $uid);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function setOwner(UserInterface $account): static {
    $this->set('uid', $account->id());
    return $this;
  }

}
