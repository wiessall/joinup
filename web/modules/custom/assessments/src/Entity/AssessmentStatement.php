<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

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
        'weight' => 0,
        'settings' => [
          'rows' => 3,
          'placeholder' => '',
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'text_default',
        'weight' => 0,
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
        'type' => 'options_select',
        'weight' => 1,
      ])
      ->setDisplayOptions('view', [
        'label' => 'above',
        'type' => 'list_default',
        'weight' => 1,
      ])
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->id();
  }

}
