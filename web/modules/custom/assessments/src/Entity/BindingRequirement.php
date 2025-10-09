<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\BindingRequirementInterface;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the binding requirement entity class.
 *
 * @ContentEntityType(
 *   id = "assessment_requirement",
 *   label = @Translation("Binding Requirement"),
 *   label_collection = @Translation("Binding Requirements"),
 *   label_singular = @Translation("binding requirement"),
 *   label_plural = @Translation("binding requirements"),
 *   label_count = @PluralTranslation(
 *     singular = "@count binding requirements",
 *     plural = "@count binding requirements",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\BindingRequirementListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\BindingRequirementForm",
 *       "edit" = "Drupal\assessments\Form\BindingRequirementForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessment_requirement",
 *   data_table = "assessment_requirement_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessment_requirement",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment-requirement",
 *     "add-form" = "/assessments/binding-requirements/add",
 *     "canonical" = "/assessments/binding-requirements/{assessment_requirement}",
 *     "edit-form" = "/assessments/binding-requirements/{assessment_requirement}/edit",
 *     "delete-form" = "/assessments/binding-requirements/{assessment_requirement}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment-requirement/delete-multiple",
 *   },
 * )
 */
final class BindingRequirement extends AssessmentContent {

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

    // Documented In URI field.
    $fields['documented_in'] = BaseFieldDefinition::create('uri')
      ->setTranslatable(TRUE)
      ->setLabel(t('Documented In'))
      ->setDescription(t('A URI of a resource that documents this legal expression.'))
      ->setDisplayOptions('form', [
        'type' => 'uri',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'uri',
        'weight' => -3,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Legal Expression field. Reference to assessment_expression.
    $fields['legal_expression'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Legal Expression'))
      ->setSetting('target_type', 'assessment_expression')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'collapsible' => TRUE,
          'collapsed' => TRUE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -2,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Asset field. Reference to assessment_asset.
    $fields['asset'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Asset'))
      ->setSetting('target_type', 'assessments_asset')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'collapsible' => TRUE,
          'collapsed' => TRUE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -1,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Documents. File reference field.
    $fields['documents'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Documents'))
      ->setTranslatable(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setSetting('target_type', 'media')
      ->setSetting('handler', 'default:media')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'file' => 'file',
        ],
      ])
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'collapsible' => TRUE,
          'collapsed' => TRUE,
        ],
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
      ->setDescription(t('Reference to supporting documents for the binding requirement.'));

    // Affected Party field. Reference to assessment_service.
    $fields['affected_party'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Affected Party'))
      ->setSetting('target_type', 'assessment_service')
      ->setSetting('handler', 'default')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'collapsible' => TRUE,
          'collapsed' => TRUE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -4,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Participant field. Reference to assessment_participation.
    $fields['participant'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Participant'))
      ->setSetting('target_type', 'assessments_participation')
      ->setSetting('handler', 'default')
      ->setRequired(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_simple',
        'settings' => [
          'collapsible' => TRUE,
          'collapsed' => TRUE,
        ],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'entity_reference_label',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setLabel(t('Status'))
      ->setDefaultValue(TRUE)
      ->setSetting('on_label', 'Enabled')
      ->setDisplayOptions('form', [
        'type' => 'boolean_checkbox',
        'settings' => [
          'display_label' => FALSE,
        ],
        'weight' => 0,
      ])
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
      ->setDescription(t('The time that the binding requirement was created.'))
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
      ->setDescription(t('The time that the binding requirement was last edited.'));

    return $fields;
  }

}
