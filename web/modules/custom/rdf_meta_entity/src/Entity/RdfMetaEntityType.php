<?php

declare(strict_types = 1);

namespace Drupal\rdf_meta_entity\Entity;

use Drupal\meta_entity\Entity\MetaEntityType;

/**
 * Defines the Meta Entity Type configuration entity.
 *
 * @ConfigEntityType(
 *   id = "rdf_meta_entity_type",
 *   label = @Translation("RDF meta entity type"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\meta_entity\Form\MetaEntityTypeForm",
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "list_builder" = "\Drupal\rdf_meta_entity\RdfMetaEntityTypeListBuilder",
 *   },
 *   admin_permission = "administer rdf meta entity",
 *   bundle_of = "rdf_meta_entity",
 *   config_prefix = "type",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "collection" = "/admin/structure/rdf-meta-entity",
 *     "edit-form" = "/admin/structure/rdf-meta-entity/manage/{rdf_meta_entity_type}",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "description",
 *     "mapping",
 *   },
 * )
 */
class RdfMetaEntityType extends MetaEntityType {}
