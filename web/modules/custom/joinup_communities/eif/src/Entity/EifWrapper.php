<?php

declare(strict_types = 1);

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
 *   },
 *   links = {
 *     "add-form" = "/admin/content/eif-wrapper/add",
 *     "canonical" = "/admin/content/eif-wrapper/manage/{eif_wrapper}",
 *     "edit-form" = "/admin/content/eif-wrapper/manage/{eif_wrapper}/edit",
 *     "delete-form" = "/admin/content/eif-wrapper/manage/{eif_wrapper}/delete",
 *     "collection" = "/admin/content/eif-wrapper"
 *   },
 *   field_ui_base_route = "entity.eif_wrapper.settings",
 * )
 *
 * @todo Move the whole logic to a custim field type once ISAICP-3576 lands.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3576
 */
class EifWrapper extends ContentEntityBase implements EifWrapperInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $vocabularies = [
      'eif_conceptual_model',
      'eif_interoperability_layer',
      'eif_principle',
      'eif_recommendation',
    ];

    $fields['id'] = BaseFieldDefinition::create('uri')
      ->setLabel(t('ID'))
      ->setTranslatable(FALSE);

    $fields['term'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Term'))
      ->setDescription(t('Reference to EIF layer, model, principle or recommendation'))
      ->setSetting('target_type', 'taxonomy_term')
      ->setSettings([
        'handler' => 'default:taxonomy_term',
        'handler_settings' => [
          'target_bundles' => array_combine($vocabularies, $vocabularies),
        ],
      ])
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setCardinality(1)
      ->setRequired(TRUE);

    $fields['description'] = BaseFieldDefinition::create('text_long')
      ->setLabel(t('Description'))
      ->setDescription(t('A description of the EIF wrapper.'))
      ->setRevisionable(FALSE)
      ->setTranslatable(FALSE)
      ->setCardinality(1);

    return $fields;
  }

}
