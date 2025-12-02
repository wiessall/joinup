<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\assessments\AssessmentVocabularyHelper;

/**
 * Defines the Assessment statement entity.
 *
 * Bundles of this entity type are defined in code.
 *
 * @see assessments_entity_bundle_info()
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
 * )
 */
final class AssessmentStatement extends ContentEntityBase implements AssessmentResourceInterface {

  use AssessmentResourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getResourcePropertyMappings(): array {
    return [
      'interpretation' => [
        'field_name' => 'interpretation',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'judgement' => [
        'field_name' => 'judgement',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'type' => [
        'field_name' => 'statement_type',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function translateResourceValue(string $fieldName, mixed $values): mixed {
    switch ($fieldName) {
      case 'interpretation':
      case 'judgement':
      case 'statement_type':
        return $values;
      default:
        return $values;
    }
  }

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

    $fields['statement_type'] = BaseFieldDefinition::create('entity_reference')
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

    $fields['landing_page'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Statement supporting document'))
      ->setDescription(t('The supporting document for the assessment statement.'))
      ->setSetting('file_extensions', 'txt doc docx pdf ppt pptx xls xlsx')
      ->setSetting('file_directory', 'assessment-statements')
      ->setSetting('uri_scheme', 'public')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
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

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage): void {
    parent::preSave($storage);

    // An RDF mapping is needed for the statement type because we cannot map the
    // bundle. The 'type' is kept in sync with the bundle value.
    $tid = \Drupal::service(AssessmentVocabularyHelper::class)
      ->getTermIdFromIdentifier('assess_statement_type', $this->bundle());
    $this->set('statement_type', $tid);
  }

}
