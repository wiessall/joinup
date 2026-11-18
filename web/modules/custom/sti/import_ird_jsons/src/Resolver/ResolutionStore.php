<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;

/**
 * Remembers non-exact resolutions, so a re-import links each name the same way as before.
 *
 * Exact matches are not stored: they are cheap to redo and stay right. Fuzzy, acronym, LLM and generated ones
 * are, including an LLM's "none" (target NULL), so the model is asked once per name.
 *
 * Kept in Drupal's key-value store (collection "import_ird_jsons.resolution"), so nothing needs installing.
 * To review: ddev drush php:eval 'print_r(\Drupal::keyValue("import_ird_jsons.resolution")->getAll());'
 * To forget everything: ddev drush php:eval '\Drupal::keyValue("import_ird_jsons.resolution")->deleteAll();'
 * A migration's rollback forgets the entities it generated (forgetGenerated()).
 */
final class ResolutionStore {

  public const COLLECTION = 'import_ird_jsons.resolution';

  private readonly KeyValueStoreInterface $store;

  public function __construct(KeyValueFactoryInterface $keyValue) {
    $this->store = $keyValue->get(self::COLLECTION);
  }

  /**
   * @return array{target: ?string, tier: string}|null
   */
  public function get(string $scope, string $input): ?array {
    return $this->store->get(self::key($scope, $input));
  }

  /**
   *
   */
  public function set(string $scope, string $input, ?string $target, string $tier, float $score, ?string $migration = NULL): void {
    $this->store->set(self::key($scope, $input), [
      'target' => $target,
      'tier' => $tier,
      'score' => $score,
      'input' => $input,
      'scope' => $scope,
      'migration' => $migration,
      'created' => time(),
    ]);
  }

  /**
   * Forgets the entities a migration generated, and its generated entities since deleted, so importing again
   * starts over for them. Matches and LLM answers are kept: they still hold, and an LLM call is slow.
   *
   * @return int
   *   How many were forgotten.
   */
  public function forgetGenerated(string $migration): int {
    $keys = array_keys(array_filter($this->store->getAll(), fn($v) =>
      ($v['migration'] ?? NULL) === $migration && in_array($v['tier'] ?? '', ['generated', 'deleted'], TRUE)));
    $this->store->deleteMultiple($keys);
    return count($keys);
  }

  /**
   *
   */
  public function delete(string $scope, string $input): void {
    $this->store->delete(self::key($scope, $input));
  }

  /**
   * The input as compared, folded, then hashed so any length fits the key column.
   */
  private static function key(string $scope, string $input): string {
    return $scope . ':' . hash('sha256', NameMatcher::fold($input));
  }

}
