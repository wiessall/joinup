<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the proposed collections and solutions.
 *
 * @Block(
 *   id = "proposed_entities",
 *   admin_label = @Translation("Rdf entities in proposed state")
 * )
 */
class ProposedEntitiesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new ProposedEntitiesBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected EntityTypeManagerInterface $entityTypeManager) {
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
      $container->get('entity_type.manager')
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
   * Receives the unpublished content rows for the current user.
   *
   * @return array
   *   An array of rows to render.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the Node entity type definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the Node entity type is not defined.
   */
  protected function getRows(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', ['solution', 'collection'], 'IN')
      ->condition('field_state', 'proposed')
      ->sort('created', 'DESC');
    $results = $query->execute();
    $entities = $storage->loadMultiple($results);

    $rows = [];
    foreach ($entities as $entity) {
      $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'view_mode_tile');
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
