<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Reads the meta block of the intake file behind a migration.
 *
 * Migrate hands process plugins one record at a time, and meta sits outside
 * the records. This reads the migration's source file once per run, through
 * the migration's own data fetcher, and caches the parsed meta.
 */
final class IntakeFile {

  /** @var array<string, \Drupal\import_ird_jsons\IrdFileMeta> */
  private array $meta = [];

  public function __construct(
    private readonly PluginManagerInterface $dataFetcherManager,
  ) {}

  /**
   * @throws \InvalidArgumentException|\JsonException|\LogicException
   */
  public function meta(MigrationInterface $migration): IrdFileMeta {
    $source = $migration->getSourceConfiguration();
    $urls = array_values((array) ($source['urls'] ?? []));
    if (count($urls) !== 1) {
      // One file is one collection; several files would mean several metas.
      throw new \LogicException(sprintf('migration %s must read exactly one intake file, it lists %d.', $migration->id(), count($urls)));
    }
    $url = (string) $urls[0];
    $key = $migration->id() . '|' . $url;

    if (!isset($this->meta[$key])) {
      /** @var \Drupal\migrate_plus\DataFetcherPluginInterface $fetcher */
      $fetcher = $this->dataFetcherManager->createInstance($source['data_fetcher_plugin'] ?? 'file', $source);
      $document = json_decode($fetcher->getResponseContent($url), TRUE, 512, JSON_THROW_ON_ERROR);
      $this->meta[$key] = IrdFileMeta::fromDocument($document);
    }
    return $this->meta[$key];
  }

}
