<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eif\EifInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the block that displays the Eif filters.
 */
#[Block(
  id: "eif_filter_block",
  admin_label: new TranslatableMarkup("EIF filter block"),
  category: new TranslatableMarkup("EIF"),
)]
class EifFilterBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
    $tree = [];

    $term_storage = $this->entityTypeManager->getStorage('taxonomy_term');

    $vocabularies = [
      'eif_solution_type' => [
        'name' => $this->t('Solution category'),
        'nid'  => EifInterface::EIF_SOLUTION_CATEGORY_FILTER_TARGET_ID,
      ],
      'eif_principle' => [
        'name' => $this->t('Underlying principles'),
        'nid'  => EifInterface::EIF_PRINCIPLE_FILTER_TARGET_ID,
      ],
      'eif_interoperability_layer' => [
        'name' => $this->t('Interoperability layers'),
        'nid'  => EifInterface::EIF_LAYER_FILTER_TARGET_ID,
      ],
      'eif_conceptual_model' => [
        'name' => $this->t('Conceptual model'),
        'nid'  => EifInterface::EIF_MODEL_FILTER_TARGET_ID,
      ],
    ];
    foreach ($vocabularies as $machine_name => $vocabulary) {
      $principle_terms = $term_storage->loadTree($machine_name);
      $id = htmlentities(str_replace([' ', '/', ':'], ['-', '-', ''], strtolower((string) $vocabulary['name'])));

      foreach ($principle_terms as $term) {
        $tree = $this->getTree($id, (string) $vocabulary['name'], $tree, $term, $term_storage, $vocabulary['nid']);
      }
    }

    if (empty($tree)) {
      return [];
    }

    $build['description'] = [
      '#theme' => 'eif_description',
      '#title' => $this->t('The EIF Solutions page counts with an online assistant that helps users to find operational solutions covering the alignment and implementation of the European Interoperability Framework and filter them by attributes.'),
      '#steps' => [
        $this->t('Step 1 - Choose one or combine multiple filters to find solutions that match specifically your EIF requirements'),
        $this->t('Step 2 - Explore operational solutions that help align to the European Interoperability Framework'),
      ],
    ];

    $build['tree_filters'] = [
      '#theme' => 'eif_filter_list',
      '#items' => $tree,
    ];
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
   * @param int|null $nid
   *   EIF Pillars node id.
   *
   * @return array
   *   Added items for filters.
   */
  public function getTree(string $id, string $name, array $tree, \stdClass $term, EntityStorageInterface $storage, ?int $nid): array {
    $tree[$id]['title'] = $nid ? Link::createFromRoute($name, 'entity.node.canonical', ['node' => $nid]) : $name;
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
