<?php

declare(strict_types=1);

namespace Drupal\joinup_search\Plugin\search_api\processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\state_machine_revisions\RevisionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Ensures that the item to be indexed is the latest version.
 */
#[SearchApiProcessor(
  id: "joinup_entity_latest_revision",
  label: new TranslatableMarkup("Joinup entity latest revision"),
  description: new TranslatableMarkup("Ensures that the version of the entity is the latest revision."),
  stages: [
    "alter_items" => -20,
  ],
)]
class JoinupEntityLatestRevision extends ProcessorPluginBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RevisionManagerInterface $revisionManager,
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
      $container->get('entity_type.manager'),
      $container->get('state_machine_revisions.revision_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    /** @var \Drupal\search_api\Item\Item[] $items */
    foreach ($items as $item_id => $item) {
      $original_object = $item->getOriginalObject();
      $object = $original_object->getValue();
      if ($object instanceof NodeInterface) {
        if (!$this->revisionManager->isLatestRevision($object)) {
          $latest = $this->revisionManager->loadLatestRevision($object);
          if (!empty($latest)) {
            $original_object->setValue($latest);
            $item->setOriginalObject($original_object);
            $items[$item_id] = $item;
          }
        }
      }
    }
  }

}
