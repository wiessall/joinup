<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\meta_entity\Entity\MetaEntityTypeInterface;

/**
 * Defines the Meta Entity Type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "rdf_meta_entity_type",
 *   label = @Translation("Rdf meta entity type"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\Core\Entity\ContentEntityForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "\Drupal\rdf_meta_entity\RdfMetaEntityTypeListBuilder",
 *   },
 *   admin_permission = "administer rdf meta entity",
 *   bundle_of = "rdf_meta_entity",
 *   config_prefix = "rid",
 *   entity_keys = {
 *     "id" = "rid",
 *     "label" = "name",
 *   },
 *   links = {
 *     "collection" = "/admin/structure/rdf-meta-entity",
 *     "edit-form" = "/admin/structure/rdf-meta-entity/manage/{rdf_meta_entity_type}",
 *   },
 *   config_export = {
 *     "rid",
 *     "name",
 *     "description",
 *     "mapping",
 *   },
 * )
 */
class RdfMetaEntityType extends ConfigEntityBundleBase implements MetaEntityTypeInterface {

  /**
   * The entity ID.
   *
   * @var string
   */
  protected $rid;

  /**
   * The human-readable name of the meta entity type.
   *
   * @var string
   */
  protected $label;

  /**
   * The description of the meta entity type.
   *
   * @var string
   */
  protected $description;

  /**
   * {@inheritdoc}
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function id() {
    return $this->rid;
  }

}
