<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Adds all ancestors' IDs to the topic aggregated hierarchical field.
 *
 * This is currently hardcoded on topics since it is the only hierarchical data
 * we are indexing right now. In the future this can be expanded to loop over
 * all fields and check if any are hierarchical.
 */
#[SearchApiProcessor(
  id: "aggregated_topic_hierarchy",
  label: new TranslatableMarkup("Index aggregated topic hierarchy"),
  description: new TranslatableMarkup("Allows the indexing of values along with all their ancestors for the topic aggregated hierarchical fields."),
  stages: [
    "preprocess_index" => -50,
  ],
)]
class AddAggregatedTopicHierarchy extends ProcessorPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function preprocessIndexItems(array $items) {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    foreach ($items as $item) {
      if (!($field = $item->getField('topic'))) {
        continue;
      }

      $parent_tids = [];
      foreach ($values = $field->getValues() as $tid) {
        $parents = $storage->loadParents($tid);
        $parent_tids = array_merge($parent_tids, array_keys($parents));
      }

      $field->setValues(array_merge($values, $parent_tids));
    }
  }

}
