<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the proposed collections and solutions.
 */
#[Block(
  id: "proposed_entities",
  admin_label: new TranslatableMarkup("Rdf entities in proposed state"),
)]
class ProposedEntitiesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $rows = $this->getRows();
    if (empty($rows)) {
      return [];
    }
    $build = [
      // The 'listing' child key is needed to avoid copying the #attributes to
      // the parent block.
      // @see \Drupal\block\BlockViewBuilder::preRender()
      '#extra_suggestion' => 'block__separated',
      'listing' => [
        '#type' => 'container',
        '#extra_suggestion' => 'container__grid',
      ],
    ];

    $build['listing'] += $rows;
    return $build;
  }

  /**
   * Receives the proposed versions of groups.
   *
   * @return array
   *   An array of rows to render.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the group plugin definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *    Thrown if the group doesn't exist.
   */
  protected function getRows(): array {
    $query = $this->database->select('node_revision', 'nr')
      ->fields('nr', ['nid', 'vid']);
    $query->innerJoin('node_field_data', 'nfd', 'nfd.nid = nr.nid');
    $query->innerJoin('node_revision__field_state', 'nrfs', 'nrfs.revision_id = nr.vid');
    $query->condition('nfd.type', ['solution', 'collection'], 'IN')
      ->condition('nrfs.field_state_value', 'proposed');
    // Subquery to find nodes with newer non-proposed states, because
    // if newer states are found it means the proposal is already irrelevant.
    $subquery = $this->database->select('node_revision', 'nr2');
    $subquery->fields('nr2', ['nid'])
      ->innerJoin('node_revision__field_state', 'nrfs2', 'nrfs2.revision_id = nr2.vid');
    $subquery->condition('nrfs2.field_state_value', 'proposed', '<>');
    $subquery->where('nr2.nid = nr.nid AND nr2.vid > nr.vid');
    $query->notExists($subquery);
    $results = $query->execute()->fetchAll();

    // Pass all results into an array to load them.
    $nids = array_map(function ($el) {
      return $el->nid;
    }, $results);
    $storage = $this->entityTypeManager->getStorage('node');
    $nodes = $storage->loadMultiple($nids);

    $rows = [];
    foreach ($nodes as $node) {
      $view = $this->entityTypeManager->getViewBuilder($node->getEntityTypeId())->view($node, 'view_mode_tile');
      $rows[] = [
        '#type' => 'container',
        '#extra_suggestion' => 'container__grid_item',
        'entity' => $view,
      ];
    }
    return $rows;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $nodeType = $this->entityTypeManager->getStorage('node')->getEntityType();
    return Cache::mergeTags(parent::getCacheTags(), $nodeType->getListCacheTags());
  }

}
