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
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['interpretation'] = BaseFieldDefinition::create('list_integer')
      ->setLabel(t('Interpretation'))
      ->setDescription(t('The interpretation of the statement.'))
      ->setTranslatable(FALSE)
      ->setSettings([
        'allowed_values' => [
          1 => 'Positive',
          0 => 'Neutral',
          -1 => 'Negative',
        ],
      ])
      ->setDisplayOptions('form', [
        'type' => 'options_buttons',
        'weight' => 0,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 0,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['landing_page'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Statement supporting document'))
      ->setDescription(t('The supporting document for the assessment statement.'))
      ->setSetting('file_extensions', 'txt doc docx pdf ppt pptx xls xlsx')
      ->setSetting('file_directory', 'assessment-statements')
      ->setSetting('uri_scheme', 'public')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'uri_link',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 2,
        'settings' => [
          'progress_indicator' => 'throbber',
          'display_field' => FALSE,
        ],
      ])
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', FALSE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): ?string {
    return $this->id();
  }

}
