<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\assessments\AssessmentInterface;
use Drupal\ctools\Testing\EntityCreationTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the assessment entity class.
 *
 * @ContentEntityType(
 *   id = "assessment",
 *   label = @Translation("Assessment"),
 *   label_collection = @Translation("Assessments"),
 *   label_singular = @Translation("assessment"),
 *   label_plural = @Translation("assessments"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessments",
 *     plural = "@count assessments",
 *   ),
 *   bundle_label = @Translation("Assessment type"),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\AssessmentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\assessments\AssessmentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\assessments\Routing\AssessmentsHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessment",
 *   data_table = "assessment_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessment types",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "type",
 *     "label" = "name",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment",
 *     "add-form" = "/assessments/add/{assessment_type}",
 *     "add-page" = "/assessments/add",
 *     "canonical" = "/assessments/{assessment}",
 *     "edit-form" = "/assessments/{assessment}/edit",
 *     "delete-form" = "/assessments/{assessment}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment/delete-multiple",
 *   },
 *   bundle_entity_type = "assessment_type",
 *   field_ui_base_route = "entity.assessment_type.edit_form",
 * )
 */
class Assessment extends ContentEntityBase implements AssessmentInterface {

  use EntityChangedTrait;
  use EntityCreationTrait;
  use EntityOwnerTrait;
  use RdfSyncEntityTrait;
  use StringTranslationTrait;

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

    $fields['name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Name'))
      ->setDescription(t('The name or short description.'))
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('form', TRUE)
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
        'weight' => 5,
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

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setTranslatable(TRUE)
      ->setLabel(t('Author'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayOptions('form', [
        'type' => 'entity_reference_autocomplete',
        'settings' => [
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
        'weight' => 15,
      ])
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
      ->setDescription(t('The time that the assessment was created.'))
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('form', [
        'type' => 'datetime_timestamp',
        'weight' => 20,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setTranslatable(TRUE)
      ->setDescription(t('The time that the assessment was last edited.'));

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->get('name')?->value ?? $this->t('@bundle #@id', [
      '@bundle' => $this->type->entity->label(),
      '@id' => $this->id(),
    ])->render();
  }

}
