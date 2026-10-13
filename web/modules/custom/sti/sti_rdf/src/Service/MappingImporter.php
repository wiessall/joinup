<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Brings rdf_sync mappings from config/sync into active configuration.
 *
 * Only the 'third_party_settings.rdf_sync' subtree, and only on config that
 * already exists on the site. It is a narrow stand-in for config import, for
 * when a full import is blocked by unrelated differences: the graph mappings
 * (RDF type, URI plugin, predicates) must match the code that reads them, or
 * every sync writes the previous vocabulary under the new URIs.
 *
 * Written through the config factory, not by saving the bundle or field
 * entities: only this one key changes, and entity hooks reacting to a
 * changed mapping are not wanted here. Callers rebuild caches afterwards.
 *
 * Config that exists in config/sync but not on the site (new fields, new
 * paragraph types) cannot be created this way and is reported instead.
 */
final class MappingImporter {

  private const KEY = 'third_party_settings.rdf_sync';

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly StorageInterface $syncStorage,
  ) {}

  /**
   * Compares the rdf_sync mappings in config/sync with the active ones.
   *
   * @return array{changed: array<string, array{from: mixed, to: mixed}>, missing: string[]}
   *   'changed': config whose mapping differs, with the active ('from') and
   *   the config/sync ('to') mapping; 'to' is NULL when config/sync has no
   *   mapping for it any more. 'missing': config with a mapping in
   *   config/sync that does not exist on the site.
   */
  public function diff(): array {
    $changed = [];
    $missing = [];

    foreach ($this->syncStorage->listAll() as $name) {
      $data = $this->syncStorage->read($name);
      $wanted = is_array($data) ? ($data['third_party_settings']['rdf_sync'] ?? NULL) : NULL;
      $config = $this->configFactory->get($name);
      if ($config->isNew()) {
        if ($wanted !== NULL) {
          $missing[] = $name;
        }
        continue;
      }
      $active = $config->get(self::KEY);
      if ($wanted === NULL && $active === NULL) {
        continue;
      }
      if (self::normalise($wanted) !== self::normalise($active)) {
        $changed[$name] = ['from' => $active, 'to' => $wanted];
      }
    }

    ksort($changed);
    sort($missing);
    return ['changed' => $changed, 'missing' => $missing];
  }

  /**
   * Writes the config/sync mappings into active configuration.
   *
   * @return string[]
   *   Names of the config changed.
   *
   * @throws \RuntimeException
   *   When a mapping still differs after being written.
   */
  public function apply(): array {
    $names = [];
    foreach ($this->diff()['changed'] as $name => $change) {
      $config = $this->configFactory->getEditable($name);
      if ($change['to'] === NULL) {
        $config->clear(self::KEY);
      }
      else {
        $config->set(self::KEY, $change['to']);
        // rdf_sync reads the mapping only; the dependency keeps a later
        // config export identical to config/sync.
        $modules = (array) $config->get('dependencies.module');
        if (!in_array('rdf_sync', $modules, TRUE)) {
          $modules[] = 'rdf_sync';
          sort($modules);
          $config->set('dependencies.module', $modules);
        }
      }
      $config->save();
      $names[] = $name;
    }

    $left = array_keys($this->diff()['changed']);
    if ($left) {
      throw new \RuntimeException(sprintf('rdf_sync mappings still differ after import: %s.', implode(', ', $left)));
    }
    return $names;
  }

  /**
   * Makes two mappings comparable regardless of key order.
   */
  private static function normalise(mixed $value): mixed {
    if (!is_array($value)) {
      return $value;
    }
    ksort($value);
    return array_map([self::class, 'normalise'], $value);
  }

}
