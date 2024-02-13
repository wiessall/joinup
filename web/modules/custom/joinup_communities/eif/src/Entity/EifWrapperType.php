<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;

/**
 * Defines the EIF Wrapper type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "eif_wrapper_type",
 *   label = @Translation("EIF Wrapper Type"),
 *   handlers = {
 *     "form" = {
 *       "add" = "Drupal\eif\Form\EifWrapperTypeForm",
 *       "edit" = "Drupal\eif\Form\EifWrapperTypeForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "Drupal\eif\EifWrapperTypeListBuilder",
 *   },
 *   links = {
 *     "add-form" = "/admin/structure/eif-wrapper/add",
 *     "edit-form" = "/admin/structure/eif-wrapper/manage/{eif_wrapper_type}",
 *     "delete-form" = "/admin/structure/eif-wrapper/manage/{eif_wrapper_type}/delete",
 *     "collection" = "/admin/structure/eif-wrapper",
 *   },
 *   admin_permission = "administer rdf meta entity",
 *   bundle_of = "eif_wrapper",
 *   config_prefix = "eif_wrapper_type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 * )
 */
class EifWrapperType extends ConfigEntityBundleBase {

  /**
   * The entity ID.
   *
   * @var string
   */
  protected string $id;

  /**
   * The human-readable name of the EIF Wrapper type.
   *
   * @var string
   */
  protected string $label;

}
