<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Assessment content type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "assessment_type",
 *   label = @Translation("Assessment content type"),
 *   label_collection = @Translation("Assessment content types"),
 *   label_singular = @Translation("assessment content type"),
 *   label_plural = @Translation("assessment contents types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessment contents type",
 *     plural = "@count assessment contents types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentContentTypeForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentContentTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\assessments\AssessmentContentTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer assessment_content types",
 *   bundle_of = "assessment_content",
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
 *   },
 * )
 */
final class AssessmentType extends ConfigEntityBundleBase {

  /**
   * The machine name of this assessment content type.
   */
  protected string $id;

  /**
   * The human-readable name of the assessment content type.
   */
  protected string $label;

}
