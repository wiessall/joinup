<?php

declare(strict_types=1);

namespace Drupal\ai_enhancement\Tool;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Lookup tool: query taxonomy vocabularies for terms matching a string.
 *
 * The model uses this to ground its annotations in the actual taxonomies
 * present in the portal — preventing hallucinated category names.
 */
class TaxonomyLookupTool implements ToolInterface {

  private const MAX_RESULTS = 25;

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function getName(): string {
    return 'taxonomy_lookup';
  }

  public function getSchema(): array {
    return [
      'type' => 'function',
      'function' => [
        'name' => $this->getName(),
        'description' => 'Look up taxonomy terms by partial-name match within a vocabulary. Use this before assigning category-like values to confirm the term exists. Returns matching terms with their IDs, names, and descriptions.',
        'parameters' => [
          'type' => 'object',
          'properties' => [
            'vocabulary' => [
              'type' => 'string',
              'description' => 'Machine name of the vocabulary to search (e.g. "topics", "innovation_areas").',
            ],
            'query' => [
              'type' => 'string',
              'description' => 'Substring to search for. Case-insensitive. Empty string returns the first N terms.',
            ],
            'limit' => [
              'type' => 'integer',
              'description' => 'Maximum number of results to return (default 10, max 25).',
            ],
          ],
          'required' => ['vocabulary'],
        ],
      ],
    ];
  }

  public function execute(array $arguments): array {
    $vocabulary = (string) ($arguments['vocabulary'] ?? '');
    $query = trim((string) ($arguments['query'] ?? ''));
    $limit = min(self::MAX_RESULTS, max(1, (int) ($arguments['limit'] ?? 10)));

    if ($vocabulary === '') {
      return ['error' => 'vocabulary is required'];
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $query_builder = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', $vocabulary)
      ->range(0, $limit)
      ->sort('name');

    if ($query !== '') {
      $query_builder->condition('name', $query, 'CONTAINS');
    }

    $ids = $query_builder->execute();
    if (empty($ids)) {
      return ['matches' => [], 'count' => 0];
    }

    $terms = $storage->loadMultiple($ids);
    $matches = [];
    foreach ($terms as $term) {
      $matches[] = [
        'tid' => (int) $term->id(),
        'name' => (string) $term->label(),
        'description' => (string) $term->getDescription(),
      ];
    }

    return [
      'vocabulary' => $vocabulary,
      'query' => $query,
      'count' => count($matches),
      'matches' => $matches,
    ];
  }

}
