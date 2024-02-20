<?php

declare(strict_types=1);

namespace Drupal\eif\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\eif\EifWrapperInterface;

/**
 * Acts as a wrapper around EIF reference to term and description.
 *
 * An 'EIF Perspective' RDF Meta Entity should link a multi-value field having
 * two columns:
 * - Taxonomy term reference, pointing to EIF vocabularies,
 * - Description.
 * Currently, fields of entity types using SPARQL Entity Storage cannot have
 * multiple columns and multiple values in the same time. This is a well known
 * limitation handled in ISAICP-3576. This entity type acts in the same way as
 * the custom field type and is a temporary solution until ISAICP-3576 is fixed.
 *
 * @ContentEntityType(
 *   id = "eif_wrapper",
 *   label = @Translation("EIF Wrapper"),
 *   label_collection = @Translation("EIF Wrappers"),
 *   bundle_label = @Translation("EIF Wrapper type"),
 *   handlers = {
 *     "form" = {
 *       "default" = "Drupal\eif\Form\EifWrapperForm",
 *       "delete" = "Drupal\Core\Entity\ContentEntityDeleteForm",
 *     },
 *     "list_builder" = "Drupal\eif\EifWrapperListBuilder",
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *     "storage" = "\Drupal\sparql_entity_storage\SparqlEntityStorage",
 *   },
 *   base_table = NULL,
 *   admin_permission = "administer rdf meta entity",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "id",
 *     "bundle" = "type",
 *   },
 *   links = {
 *     "add-form" = "/admin/content/eif-wrapper/add",
 *     "canonical" = "/admin/content/eif-wrapper/manage/{eif_wrapper}",
 *     "edit-form" = "/admin/content/eif-wrapper/manage/{eif_wrapper}/edit",
 *     "delete-form" = "/admin/content/eif-wrapper/manage/{eif_wrapper}/delete",
 *     "collection" = "/admin/content/eif-wrapper"
 *   },
 *   field_ui_base_route = "entity.eif_wrapper_type.edit_form",
 *   bundle_entity_type = "eif_wrapper_type",
 * )
 *
 * @todo Move the whole logic to a custom field type once ISAICP-3576 lands.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3576
 */
class EifWrapper extends ContentEntityBase implements EifWrapperInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['id'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('ID'))
      ->setTranslatable(FALSE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setCardinality(1)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    return $this->hasField('term') ? $this->get('term')->entity->label() : $this->id();
  }

}
