<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsInterface;
use Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsTrait;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\assessments\AssessmentTypeAccessControlHandler;
use Drupal\assessments\AssessmentTypeListBuilder;
use Drupal\assessments\Form\AssessmentTypeForm;

/**
 * Defines the Assessment type configuration entity.
 */
#[ConfigEntityType(
  id: 'assessment_type',
  label: new TranslatableMarkup('Assessment type'),
  label_collection: new TranslatableMarkup('Assessment types'),
  label_singular: new TranslatableMarkup('assessment type'),
  label_plural: new TranslatableMarkup('assessment types'),
  label_count: [
    'singular' => '@count assessment type',
    'plural' => '@count assessment types',
  ],
  handlers: [
    'access' => AssessmentTypeAccessControlHandler::class,
    'form' => [
      'add' => AssessmentTypeForm::class,
      'edit' => AssessmentTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
    'list_builder' => AssessmentTypeListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
  ],
  admin_permission: 'administer assessment types',
  bundle_of: 'assessment',
  config_prefix: 'type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  links: [
    'add-form' => '/admin/structure/assessment_types/add',
    'edit-form' => '/admin/structure/assessment_types/manage/{assessment_type}',
    'delete-form' => '/admin/structure/assessment_types/manage/{assessment_type}/delete',
    'collection' => '/admin/structure/assessment_types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'label_singular',
    'label_plural',
    'label_count',
  ],
)]
final class AssessmentType extends ConfigEntityBundleBase implements EntityBundleWithPluralLabelsInterface {

  use EntityBundleWithPluralLabelsTrait;

  /**
   * The machine name of this assessment content type.
   */
  protected string $id;

  /**
   * The human-readable name of the assessment type.
   */
  protected string $label;

}
