<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\assessments\AssessmentContentInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the assessment content entity class.
 *
 * @ContentEntityType(
 *   id = "assessment_content",
 *   label = @Translation("Assessment content"),
 *   label_collection = @Translation("Assessment contents"),
 *   label_singular = @Translation("assessment content"),
 *   label_plural = @Translation("assessment contents"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessment contents",
 *     plural = "@count assessment contents",
 *   ),
 *   bundle_label = @Translation("Assessment content type"),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\AssessmentContentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\assessments\AssessmentContentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentContentForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentContentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   base_table = "assessment_content",
 *   data_table = "assessment_content_field_data",
 *   translatable = TRUE,
 *   admin_permission = "administer assessment_content types",
 *   entity_keys = {
 *     "id" = "id",
 *     "langcode" = "langcode",
 *     "bundle" = "bundle",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment-content",
 *     "add-form" = "/assessments/add/{assessment_content_type}",
 *     "add-page" = "/assessments/add",
 *     "canonical" = "/assessments/{assessment_content}",
 *     "edit-form" = "/assessments/{assessment_content}/edit",
 *     "delete-form" = "/assessments/{assessment_content}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment-content/delete-multiple",
 *   },
 *   bundle_entity_type = "assessment_content_type",
 *   field_ui_base_route = "entity.assessment_content_type.edit_form",
 * )
 */
class AssessmentContent extends ContentEntityBase implements AssessmentContentInterface, RdfSyncEntityInterface {

  use EntityChangedTrait;
  use EntityOwnerTrait;
  use RdfSyncEntityTrait;

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
      ->setDescription(t('The time that the assessment content was created.'))
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
      ->setDescription(t('The time that the assessment content was last edited.'));

    return $fields;
  }

}
