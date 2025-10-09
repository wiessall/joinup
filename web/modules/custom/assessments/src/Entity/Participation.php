<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the participation entity class.
 */
final class Participation extends AssessmentContent {

  use EntityChangedTrait;
  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);
    if (!$this->getOwnerId()) {
      // If no owner has been set explicitly, make the anonymous user the owner.
      $this->setOwnerId(0);
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['identifier'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Identifier'))
      ->setDescription(t('A unique identifier for the participation.'))
      ->setSettings([
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['role'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Role'))
      ->setDescription(t('The role of the agent in this participation (e.g. regulator, subject, implementer).'))
      // @todo Use a proper taxonomy term reference or a custom codelist entity.
      ->setSettings([
        'allowed_values' => [
          'regulator' => t('Regulator'),
          'subject' => t('Subject'),
          'implementer' => t('Implementer'),
          'assessor' => t('Assessor'),
          'reviewer' => t('Reviewer'),
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['code'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Code'))
      ->setDescription(t('A coded value referencing a controlled vocabulary for the participation.'))
      // @todo Use a proper taxonomy term reference or a custom codelist entity.
      ->setSettings([
        'allowed_values' => [
          'regulator' => t('Regulator'),
          'subject' => t('Subject'),
          'implementer' => t('Implementer'),
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['agent'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Agent'))
      ->setDescription(t('The agent involved in this participation.'))
      ->setSetting('target_type', 'assessments_agent')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setTranslatable(TRUE)
      ->setLabel(t('Description'))
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'author',
        'weight' => 15,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Authored on'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the participation was created.'))
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the participation was last edited.'));

    return $fields;
  }

}
