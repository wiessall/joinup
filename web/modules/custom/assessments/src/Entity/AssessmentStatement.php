<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

/**
 * Defines the Assessment statement entity.
 *
 * @ContentEntityType(
 *   id = "assessment_statement",
 *   label = @Translation("Assessment statement"),
 *   label_collection = @Translation("Assessment statements"),
 *   label_singular = @Translation("assessment statement"),
 *   label_plural = @Translation("assessment statements"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessment statement",
 *     plural = "@count assessment statements",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\assessments\AssessmentStatementListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer assessment statements",
 *   base_table = "assessment_statement",
 *   data_table = "assessment_statement_field_data",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *     "bundle" = "type"
 *   },
 *   links = {
 *      "collection" = "/admin/content/assessment-statement",
 *    },
 *   bundle_entity_type = "assessment_statement_type",
 *   field_ui_base_route = "entity.assessment_statement_type.edit_form",
 * )
 */
final class AssessmentStatement extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['judgement'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Judgement'))
      ->setDescription(t('The judgement statement.'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'text_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['interpretation'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Interpretation'))
      ->setDescription(t('The interpretation of the statement.'))
      ->setTranslatable(FALSE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'assess_statement_interpretation' => 'assess_statement_interpretation',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The statement interoperability type.'))
      ->setTranslatable(FALSE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'assess_statement_type' => 'assess_statement_type',
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    // Reference to the assessment document bundle.
    $fields['landing_page'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Statement supporting document'))
      ->setDescription(t('The document that supports this assessment statement.'))
      ->setSetting('target_type', 'assessment')
      ->setSetting('handler', 'default')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', [
        'type' => 'inline_entity_form',
        'weight' => 2,
        'settings' => [
          'form_mode' => 'default',
          'allow_new' => TRUE,
          'allow_existing' => FALSE,
          'match_operator' => 'CONTAINS',
          'size' => 60,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => 2,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return $this->id();
  }

}
