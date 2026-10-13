<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_rdf\Service\MappingReaderInterface;

/**
 * Decides which ATIO properties take exactly one value.
 *
 * The rule is read from the content model rather than kept as a separate
 * list: an ATIO property is single-valued when the atio_innovation field that
 * emits it (per its rdf_sync mapping) is a configurable, non-narrative field
 * with cardinality 1. Changing a field's cardinality therefore changes which
 * claims are compared, with no second place to update.
 *
 * Narrative fields and base fields (title) are excluded even when they hold
 * one value: two sources wording a description differently is the normal
 * case, not a pair of mutually exclusive statements that someone must rule
 * on. Readiness level, country of origin and start year are the kind of
 * property this is for.
 *
 * Two settings adjust the rule: 'exclusive_properties.add' for properties
 * that are single-valued in meaning but stored multi-valued, and
 * 'exclusive_properties.remove' for single-valued fields that sources may
 * legitimately disagree on without anyone needing to rule.
 */
final class ExclusiveProperties {

  /**
   * The computed set, for the duration of the request.
   *
   * @var string[]|null
   */
  private ?array $properties = NULL;

  /**
   * Field types that hold prose, never compared.
   */
  private const NARRATIVE_TYPES = ['string_long', 'text', 'text_long', 'text_with_summary'];

  public function __construct(
    private readonly MappingReaderInterface $mappingReader,
    private readonly EntityFieldManagerInterface $entityFieldManager,
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns the single-valued ATIO property URIs.
   *
   * @return string[]
   *   Property URIs, sorted.
   */
  public function all(): array {
    if ($this->properties !== NULL) {
      return $this->properties;
    }

    $storages = $this->entityFieldManager->getFieldStorageDefinitions('node');
    $found = [];
    foreach ($this->mappingReader->fieldMappings('node', ContentModel::INNOVATION) as $mapping) {
      $storage = $storages[$mapping['field']] ?? NULL;
      if ($storage === NULL || $storage->isBaseField() || $storage->getCardinality() !== 1) {
        continue;
      }
      if (in_array($storage->getType(), self::NARRATIVE_TYPES, TRUE)) {
        continue;
      }
      $found[$mapping['predicate']] = TRUE;
    }

    $settings = $this->configFactory->get('sti_dedupe.settings');
    foreach ((array) $settings->get('exclusive_properties.add') as $uri) {
      $found[(string) $uri] = TRUE;
    }
    foreach ((array) $settings->get('exclusive_properties.remove') as $uri) {
      unset($found[(string) $uri]);
    }

    $properties = array_keys($found);
    sort($properties);
    return $this->properties = $properties;
  }

  /**
   * Whether one ATIO property is single-valued.
   */
  public function isExclusive(string $atioProperty): bool {
    return in_array($atioProperty, $this->all(), TRUE);
  }

  /**
   * Forgets the computed set, after settings or field storage changed.
   */
  public function reset(): void {
    $this->properties = NULL;
  }

}
