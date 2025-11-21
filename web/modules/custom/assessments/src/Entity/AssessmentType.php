<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsInterface;
use Drupal\Core\Config\Entity\EntityBundleWithPluralLabelsTrait;

/**
 * Defines the Assessment type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "assessment_type",
 *   label = @Translation("Assessment type"),
 *   label_collection = @Translation("Assessment types"),
 *   label_singular = @Translation("assessment type"),
 *   label_plural = @Translation("assessment types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessment type",
 *     plural = "@count assessment types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentTypeForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\assessments\AssessmentTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer assessment types",
 *   bundle_of = "assessment",
 *   config_prefix = "type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/assessment_types/add",
 *     "edit-form" = "/admin/structure/assessment_types/manage/{assessment_type}",
 *     "delete-form" = "/admin/structure/assessment_types/manage/{assessment_type}/delete",
 *     "collection" = "/admin/structure/assessment_types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "label_singular",
 *     "label_plural",
 *     "label_count",
 *   },
 * )
 */
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
