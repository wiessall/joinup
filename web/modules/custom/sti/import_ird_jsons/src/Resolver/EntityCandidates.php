<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Builds a CandidateIndex from the entities of one bundle: what entity_fuzzy_lookup matches names against.
 *
 * Shared by the migrate plugin and anything else that must resolve a name the same way, such as the owner
 * review form resolving a proposed parent organisation.
 */
final class EntityCandidates {

  public function __construct(private readonly EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Which fields hold names, acronyms and codes, unless configured; fields an entity lacks are skipped.
   */
  public static function defaults(EntityTypeInterface $definition): array {
    $aliases = ['variant', 'alt_labels', 'alt_label', 'synonyms', 'aliases'];
    return [
      'bundle_key' => $definition->getKey('bundle'),
      'name_fields' => [$definition->getKey('label'), ...$aliases, ...array_map(fn($f) => "field_$f", $aliases)],
      'acronym_fields' => ['acronym', 'field_acronym'],
      'code_fields' => ['code', 'iso_code', 'iso_code_2', 'm49_code', 'field_code', 'field_iso_code', 'field_iso_code_2'],
      'threshold' => 0.9,
      'margin' => 0.05,
    ];
  }

  /**
   * @param array $config
   *   Overrides of defaults().
   *
   * @return array{index: CandidateIndex, labels: array<string, string>, hasCodes: bool}
   *   Labels are "name (acronyms)", for messages and for the LLM's candidate list.
   */
  public function build(string $entityType, string $bundle, array $config = []): array {
    $config += self::defaults($this->entityTypeManager->getDefinition($entityType));
    $storage = $this->entityTypeManager->getStorage($entityType);
    $index = new CandidateIndex((float) $config['threshold'], (float) $config['margin']);
    $labels = [];
    $hasCodes = FALSE;
    $ids = $storage->getQuery()->accessCheck(FALSE)->condition($config['bundle_key'], $bundle)->execute();
    foreach (array_chunk($ids, 200) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $entity) {
        $acronyms = self::values($entity, (array) $config['acronym_fields']);
        $codes = self::values($entity, (array) $config['code_fields']);
        $hasCodes = $hasCodes || $codes;
        $id = (string) $entity->id();
        $index->add($id, self::values($entity, (array) $config['name_fields']), $acronyms, $codes);
        $labels[$id] = (string) $entity->label() . ($acronyms ? ' (' . implode(', ', $acronyms) . ')' : '');
      }
      $storage->resetCache($chunk);
    }
    return ['index' => $index, 'labels' => $labels, 'hasCodes' => $hasCodes];
  }

  /**
   * String values of the given fields; fields the entity lacks are skipped.
   *
   * @return string[]
   */
  private static function values(ContentEntityInterface $entity, array $fields): array {
    $out = [];
    foreach ($fields as $field) {
      if (!$entity->hasField($field)) {
        continue;
      }
      foreach ($entity->get($field) as $item) {
        $v = trim((string) $item->value);
        if ($v !== '') {
          $out[] = $v;
        }
      }
    }
    return $out;
  }

}
