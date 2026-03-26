<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentStatementAccessControlHandler;
use Drupal\assessments\AssessmentStatementInterface;
use Drupal\assessments\AssessmentStatementListBuilder;
use Drupal\assessments\AssessmentVocabularyHelper;

/**
 * Defines the Assessment statement entity.
 *
 * Bundles of this entity type are defined in code.
 *
 * @see assessments_entity_bundle_info()
 */
#[ContentEntityType(
  id: 'assessment_statement',
  label: new TranslatableMarkup('Assessment statement'),
  label_collection: new TranslatableMarkup('Assessment statements'),
  label_singular: new TranslatableMarkup('assessment statement'),
  label_plural: new TranslatableMarkup('assessment statements'),
  entity_keys: [
    'id' => 'id',
    'label' => 'id',
    'uuid' => 'uuid',
    'bundle' => 'type',
  ],
  handlers: [
    'access' => AssessmentStatementAccessControlHandler::class,
    'list_builder' => AssessmentStatementListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  links: [
    'collection' => '/admin/content/assessment-statement',
  ],
  admin_permission: 'administer assessment statements',
  base_table: 'assessment_statement',
  data_table: 'assessment_statement_field_data',
  label_count: [
    'singular' => '@count assessment statement',
    'plural' => '@count assessment statements',
  ],
)]
final class AssessmentStatement extends ContentEntityBase implements AssessmentStatementInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['judgement'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Justification'))
      ->setTranslatable(TRUE)
      ->setDisplayOptions('form', [
        'type' => 'string_textarea',
        'weight' => 1,
        'settings' => [
          'rows' => 3,
          'placeholder' => '',
        ],
        'third_party_settings' => [
          'entity_form_field_label' => [
            'new_label' => 'Provide a justification',
            'rewrite_label' => 1,
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'inline',
        'type' => 'text_default',
        'weight' => 1,
      ]);

    $fields['interpretation'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Interpretation'))
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
        'third_party_settings' => [
          'entity_form_field_label' => [
            'new_label' => 'Tick the most appropriate',
            'rewrite_label' => 1,
          ],
        ],
      ])
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'entity_reference_label',
        'weight' => 0,
        'settings' => ['link_to_entity' => FALSE],
      ]);

    $fields['statement_type'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Type'))
      ->setDescription(t('The statement interoperability type.'))
      ->setTranslatable(FALSE)
      ->setSetting('target_type', 'taxonomy_term')
      ->setSetting('handler_settings', [
        'target_bundles' => [
          'assess_statement_type' => 'assess_statement_type',
        ],
      ]);

    $fields['landing_page'] = BaseFieldDefinition::create('file')
      ->setLabel(t('Statement supporting document'))
      ->setDescription(t('The supporting document for the assessment statement.'))
      ->setSetting('file_extensions', 'txt doc docx pdf ppt pptx zip rar xls xlsx')
      ->setSetting('file_directory', 'assessment-statements')
      ->setSetting('uri_scheme', 'public')
      ->setCardinality(5)
      ->setDisplayOptions('view', [
        'label' => 'hidden',
        'type' => 'uri_link',
        'weight' => 2,
      ])
      ->setDisplayOptions('form', [
        'type' => 'file_generic',
        'weight' => 2,
        'region' => 'hidden',
        'settings' => [
          'progress_indicator' => 'throbber',
          'display_field' => FALSE,
          'max_filesize' => '10 MB',
        ],
      ]);

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

  /**
   * {@inheritdoc}
   */
  public function getParentReport(): ?AssessmentReport {
    if (empty($this->bundle())) {
      return NULL;
    }

    $assessmentStorage = $this->entityTypeManager()->getStorage('assessment');
    $results = $assessmentStorage->getQuery()
      ->accessCheck(FALSE)
      // The report has 4 fields named after the bundles of the statements.
      ->condition($this->bundle(), $this->id())
      ->range(0, 1)
      ->execute();
    if (empty($results)) {
      return NULL;
    }

    return $assessmentStorage->load(key($results));
  }

}
