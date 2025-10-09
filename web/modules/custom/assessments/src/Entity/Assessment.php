<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\AssessmentInterface;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\RevisionableContentEntityBase;
use Drupal\Core\Field\BaseFieldDefinition;
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
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\AssessmentListBuilder",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *     "access" = "Drupal\assessments\AssessmentAccessControlHandler",
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *       "delete-multiple-confirm" = "Drupal\Core\Entity\Form\DeleteMultipleForm",
 *       "revision-delete" = \Drupal\Core\Entity\Form\RevisionDeleteForm::class,
 *       "revision-revert" = \Drupal\Core\Entity\Form\RevisionRevertForm::class,
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *       "revision" = \Drupal\Core\Entity\Routing\RevisionHtmlRouteProvider::class,
 *     },
 *   },
 *   base_table = "assessment",
 *   data_table = "assessment_field_data",
 *   revision_table = "assessment_revision",
 *   revision_data_table = "assessment_field_revision",
 *   show_revision_ui = TRUE,
 *   translatable = TRUE,
 *   admin_permission = "administer assessment",
 *   entity_keys = {
 *     "id" = "id",
 *     "revision" = "revision_id",
 *     "langcode" = "langcode",
 *     "label" = "id",
 *     "uuid" = "uuid",
 *     "owner" = "uid",
 *   },
 *   revision_metadata_keys = {
 *     "revision_user" = "revision_uid",
 *     "revision_created" = "revision_timestamp",
 *     "revision_log_message" = "revision_log",
 *   },
 *   links = {
 *     "collection" = "/admin/content/assessment",
 *     "add-form" = "/assessments/add",
 *     "canonical" = "/assessments/{assessment}",
 *     "edit-form" = "/assessments/{assessment}/edit",
 *     "delete-form" = "/assessments/{assessment}/delete",
 *     "delete-multiple-form" = "/admin/content/assessment/delete-multiple",
 *     "revision" = "/assessments/{assessment}/revision/{assessment_revision}/view",
 *     "revision-delete-form" = "/assessments/{assessment}/revision/{assessment_revision}/delete",
 *     "revision-revert-form" = "/assessments/{assessment}/revision/{assessment_revision}/revert",
 *     "version-history" = "/assessments/{assessment}/revisions",
 *   },
 * )
 */
final class Assessment extends AssessmentContent {

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

    // Public organization reference (inline entity form).
    $fields['organization'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Public Organization'))
      ->setDescription(t('Union entity or public sector body which is providing the report.'))
      ->setSetting('target_type', 'assessment_organization')
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => -10,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => -10,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Binding requirements field. Reference to assessment_requirement.
    $fields['requirement'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Binding Requirement'))
      ->setDescription(t('The binding requirement this assessment is based on.'))
      ->setSetting('target_type', 'assessment_requirement')
      ->setRequired(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => -5,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    // Statements field. Reference to assessment_statement.
    $fields['statements'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Statements'))
      ->setDescription(t('The statements included in this assessment.'))
      ->setSetting('target_type', 'assessment_statement')
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form_complex',
        'settings' => [],
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_entity_view',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE);

    $fields['status'] = BaseFieldDefinition::create('boolean')
      ->setRevisionable(TRUE)
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
      ->setRevisionable(TRUE)
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
      ->setDescription(t('The time that the assessment was created.'))
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

}
