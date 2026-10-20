<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\node\NodeInterface;
use Drupal\sti_rdf\Service\MappingCuration;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists the collections the user curates, with what is left to do.
 *
 * One row per collection the user may edit: facilitators see theirs,
 * moderators see all. Collections with proposals waiting come first.
 */
final class MappingOverviewController extends ControllerBase {

  public function __construct(
    private readonly MappingCuration $curation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static($container->get('sti_rdf.mapping_curation'));
  }

  /**
   * Builds the overview.
   */
  public function overview(): array {
    $storage = $this->entityTypeManager()->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', 'collection')
      ->sort('title')
      ->execute();

    $rows = [];
    foreach ($storage->loadMultiple($ids) as $collection) {
      if (!$collection instanceof NodeInterface || !$collection->access('update')) {
        continue;
      }
      $count = [MappingCuration::PROPOSED => 0, MappingCuration::ACCEPTED => 0, MappingCuration::REJECTED => 0];
      $mapped = [];
      foreach ($this->curation->mappings($collection) as $mapping) {
        $count[$this->curation->state($mapping)]++;
        $mapped[(int) $mapping->get('field_mapping_subject')->target_id] = TRUE;
      }
      $properties = $this->curation->properties($collection);
      $rows[] = [
        'data' => [
          Link::createFromRoute($collection->label(), 'sti_rdf.mappings', ['node' => $collection->id()]),
          count($properties),
          $count[MappingCuration::PROPOSED],
          $count[MappingCuration::ACCEPTED],
          $count[MappingCuration::REJECTED],
          count(array_diff_key($properties, $mapped)),
        ],
        'class' => $count[MappingCuration::PROPOSED] > 0 ? ['color-warning'] : [],
        '#proposed' => $count[MappingCuration::PROPOSED],
      ];
    }
    // Waiting proposals first; the sort is stable, so titles stay ordered.
    usort($rows, static fn(array $a, array $b): int => $b['#proposed'] <=> $a['#proposed']);
    foreach ($rows as &$row) {
      unset($row['#proposed']);
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('Collection'),
        $this->t('Properties'),
        $this->t('Proposed'),
        $this->t('Accepted'),
        $this->t('Rejected'),
        $this->t('Unmapped'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('You do not curate any collection.'),
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['node_list', 'paragraph_list', 'taxonomy_term_list'],
      ],
    ];
  }

}
