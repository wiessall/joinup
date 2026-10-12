<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons;

/**
 * The "meta" block of an IRD intake file, as written by ird_normalize.py.
 *
 * Holds what used to be repeated in each migration's field_claims config:
 * the collection (meta.collection_id) and, per claim key, whether it is
 * categorical and which separator split it (meta.field_config).
 */
final class IrdFileMeta {

  /**
   * @param array<string, array{categorical: bool, separator?: string}> $fieldConfig
   */
  private function __construct(
    public readonly string $collectionId,
    private readonly array $fieldConfig,
  ) {}

  /**
   * @throws \InvalidArgumentException
   *   When meta, meta.collection_id or meta.field_config is missing or
   *   malformed.
   */
  public static function fromDocument(mixed $document): self {
    if (!is_array($document) || !is_array($document['meta'] ?? NULL)) {
      throw new \InvalidArgumentException('the intake file has no "meta" object.');
    }
    $meta = $document['meta'];

    $collection_id = $meta['collection_id'] ?? NULL;
    if (!is_string($collection_id) || $collection_id === '') {
      throw new \InvalidArgumentException('meta.collection_id is missing or empty.');
    }

    $field_config = $meta['field_config'] ?? NULL;
    if (!is_array($field_config)) {
      throw new \InvalidArgumentException('meta.field_config is missing.');
    }
    foreach ($field_config as $key => $entry) {
      if (!is_array($entry) || !is_bool($entry['categorical'] ?? NULL)) {
        throw new \InvalidArgumentException(sprintf('meta.field_config["%s"] needs a boolean "categorical".', $key));
      }
    }

    return new self($collection_id, $field_config);
  }

  /**
   * Whether values of this claim key become vocabulary terms.
   *
   * A key without a field_config entry is literal, as before.
   */
  public function isCategorical(string $key): bool {
    return $this->fieldConfig[$key]['categorical'] ?? FALSE;
  }

  /**
   * The separator for this claim key, if the file names one.
   */
  public function separator(string $key): ?string {
    $separator = $this->fieldConfig[$key]['separator'] ?? NULL;
    return is_string($separator) && $separator !== '' ? $separator : NULL;
  }

}
