<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\StatementInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the statement entity class.
 *
 * @ContentEntityType(
 *   id = "assessment_statement",
 *   label = @Translation("Statement"),
 *   label_collection = @Translation("Statements"),
 *   label_singular = @Translation("statement"),
 *   label_plural = @Translation("statements"),
 *   label_count = @PluralTranslation(
 *     singular = "@count statements",
 *     plural = "@count statements",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\StatementListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\StatementForm",
 *       "edit" = "Drupal\assessments\Form\StatementForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessment_statement",
 *   data_table = "assessment_statement_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessment_statement",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment-statement",
 *     "add-form" = "/assessments/statements/add",
 *     "canonical" = "/assessments/statements/{assessment_statement}",
 *     "edit-form" = "/assessments/statements/{assessment_statement}/edit",
 *     "delete-form" = "/assessments/statements/{assessment_statement}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment-statement/delete-multiple",
 *   },
 * )
 */
final class Statement extends AssessmentContent {

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

    // Interpretation field.
    // @todo Code field.
    $fields['interpretation'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Interpretation'))
      ->setTranslatable(TRUE)
      ->setSettings([
        'allowed_values' => [
          '-1' => 'Negative',
          '0' => 'Neutral',
          '1' => 'Positive',
        ],
      ])
      ->setDefaultValue('unknown')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDescription(t('The interpretation of the statement.'));

    // Judgement field.
    $fields['judgement'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Judgement'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'text_textarea',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'text_default',
        'label' => 'above',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDescription(t('The judgement or assessment made in the statement.'));

    // Type field. Renamed to Statement type to avoid conflict with `type`.
    // @todo Code field.
    $fields['statement_type'] = BaseFieldDefinition::create('list_string')
      ->setLabel(t('Statement type'))
      ->setTranslatable(TRUE)
      ->setSettings([
        'allowed_values' => [
          'assessment' => t('Assessment'),
          'observation' => t('Observation'),
          'recommendation' => t('Recommendation'),
          'review' => t('Review'),
        ],
      ])
      ->setDefaultValue('assessment')
      ->setDisplayOptions('form', [
        'type' => 'options_select',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDescription(t('The type of statement.'));

    // Documents. File reference field.
    $fields['documents'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Documents'))
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default:media')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'file' => 'file',
        ],
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'above',
        'weight' => 10,
        'settings' => [
          'link' => TRUE,
        ],
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDescription(t('Reference to supporting documents for the statement.'));


    // Services referenced by the statement.
    $fields['services'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Services'))
      ->setTranslatable(TRUE)
      ->setSetting('target_type', 'assessment_service')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => 6,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => 6,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'type' => 'boolean',
        'label' => 'above',
        'weight' => 0,
        'settings' => [
          'format' => 'enabled-disabled',
        ],
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
      ->setDescription(t('The time that the statement was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the statement was last edited.'));

    return $fields;
  }

}
