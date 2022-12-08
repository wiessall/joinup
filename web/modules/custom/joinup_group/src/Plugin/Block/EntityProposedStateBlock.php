<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\rdf_entity\RdfInterface;

/**
 * Provides a block with the unpublished content of the entity for proposed state.
 *
 * @Block(
 *   id = "entity_proposed_state",
 *   admin_label = @Translation("Proposed state content of group"),
 *   context_definitions = {
 *     "og" = @ContextDefinition("entity:rdf_entity", label = @Translation("Organic group"))
 *   }
 * )
 */
class EntityProposedStateBlock extends EntityUnpublishedBlock {

  /**
   * The community content bundle IDs.
   *
   * @var array
   */
  const LISTED_BUNDLES = [
    'asset_release',
    'discussion',
    'document',
    'event',
    'news',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getRows(RdfInterface $group): array {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('unpublished');
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $index->query();

    $query->addCondition('entity_bundle', self::LISTED_BUNDLES, 'IN');
    $query->addCondition('entity_groups', $group->id());
    $query->sort('entity_created', 'DESC');

    $results = $query->execute();

    $entities = $this->getResultEntities($results);

    $rows = [];

    foreach ($entities as $weight => $entity) {
      if ($entity->getWorkFlowState() === 'proposed') {
        $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'view_mode_tile');
        $rows[$weight] = [
          '#type' => 'container',
          '#extra_suggestion' => 'container__grid_item',
          '#weight' => $weight,
          $weight => $view,
        ];
      }
    }
    return $rows;
  }

}
