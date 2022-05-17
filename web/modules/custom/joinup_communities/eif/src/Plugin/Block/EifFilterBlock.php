<?php

declare(strict_types = 1);

namespace Drupal\eif\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\eif\EifInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the block that displays the Eif filters.
 *
 * @Block(
 *   id = "eif_filter_block",
 *   admin_label = @Translation("Eif filter block"),
 *   category = @Translation("EIF")
 * )
 */
class EifFilterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new EifFilterBlock.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entityTypeManager;
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
  public function build(): array {
    $tree = [];
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    // Underlying principles.
    $principle_terms = $storage->loadTree('eif_principle');
    foreach ($principle_terms as $term) {
      $name = $this->t('Underlying principles');
      $id = htmlentities(
        str_replace([' ', '/', ':'], ['-', '-', ''], strtolower((string) $name))
      );
      $tree = $this->getTree($id, (string) $name, $tree, $term, $storage);
    }
    // Interoperability layer.
    $layer_terms = $storage->loadTree('eif_interoperability_layer');
    foreach ($layer_terms as $term) {
      $name = $this->t('Interoperability layer');
      $id = htmlentities(
        str_replace([' ', '/', ':'], ['-', '-', ''], strtolower((string) $name))
      );
      $tree = $this->getTree($id, (string) $name, $tree, $term, $storage);
    }
    // Conceptual model.
    $model_terms = $storage->loadTree('eif_conceptual_model');
    foreach ($model_terms as $term) {
      $name = $this->t('Conceptual model');
      $id = htmlentities(
        str_replace([' ', '/', ':'], ['-', '-', ''], strtolower((string) $name))
      );
      $tree = $this->getTree($id, (string) $name, $tree, $term, $storage);
    }
    // Solution category.
    foreach (EifInterface::EIF_CATEGORIES as $category => $label) {
      $name = $this->t('Solution category');
      $tree['solution-category']['title'] = $name;
      $tree['solution-category']['class'] = 'eif-filter--solution-category';

      $tree['solution-category']['items'][] = [
        'title' => $label,
        'description' => NULL,
        'eif_category' => $category,
      ];
    }

    if (empty($tree)) {
      return [];
    }

    $build['tree_filters'] = [
      '#theme' => 'eif_filter_list',
      '#items' => $tree,
    ];

    $build['#cache']['max-age'] = 0;

    $build['#attached']['library'][] = 'eif/eif-filter';

    return $build;
  }

  /**
   * Get taxonomy term data.
   *
   * @param string $id
   *   Filter identify.
   * @param string $name
   *   Filter name.
   * @param array $tree
   *   Items in filter.
   * @param object $term
   *   Taxonomy term.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   *
   * @return array
   *   Added items for filters.
   */
  public function getTree(string $id, string $name, array $tree, \stdClass $term, EntityStorageInterface $storage): array {
    $tree[$id]['title'] = $name;
    $tree[$id]['class'] = 'eif-filter--' . $id;

    // @todo Use an alternative title instead of dynamically splitting it.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7110.
    $parts = explode(':', $term->name);
    $name = empty($parts[1]) ? $term->name : trim($parts[1]);

    $tree[$id]['items'][] = [
      'title' => $name,
      'description' => $storage->load($term->tid)->getDescription(),
      'eif_category' => htmlentities(
        str_replace([' ', '/', ':'], ['-', '-', ''], strtolower($term->name))
      ),
    ];
    return $tree;
  }

}
