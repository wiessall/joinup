<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the Assessment statement type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "assessment_statement_type",
 *   label = @Translation("Assessment statement type"),
 *   label_collection = @Translation("Assessment statement types"),
 *   label_singular = @Translation("assessment statement type"),
 *   label_plural = @Translation("assessment statement types"),
 *   label_count = @PluralTranslation(
 *     singular = "@count assessment statement type",
 *     plural = "@count assessment statement types",
 *   ),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\assessments\Form\AssessmentStatementTypeForm",
 *       "edit" = "Drupal\assessments\Form\AssessmentStatementTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\assessments\AssessmentStatementTypeListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   admin_permission = "administer assessment statement types",
 *   bundle_of = "assessment_statement",
 *   config_prefix = "assessment_statement_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/assessment-statement-types/add",
 *     "edit-form" = "/admin/structure/assessment-statement-types/manage/{assessment_statement_type}",
 *     "delete-form" = "/admin/structure/assessment-statement-types/manage/{assessment_statement_type}/delete",
 *     "collection" = "/admin/structure/assessment-statement-types",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *   },
 * )
 */
final class AssessmentStatementType extends ConfigEntityBundleBase {

  /**
   * The machine name of this assessment statement type.
   */
  protected string $id;

  /**
   * The human-readable name of the assessment statement type.
   */
  protected string $label;

}
