<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Hook;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\sti_rdf\Service\MappingCuration;

/**
 * Reports mappings whose ATIO property no longer exists.
 *
 * A predicate removed from atio_innovation's rdf_sync mapping leaves the
 * mappings that target it in place, and nothing else says so.
 */
final class MappingRequirements {

  use StringTranslationTrait;

  public function __construct(
    private readonly MappingCuration $curation,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $orphans = $this->curation->orphans();
    if (!$orphans) {
      return ['sti_rdf_mapping_targets' => [
        'title' => $this->t('SKOS mappings to ATIO'),
        'value' => $this->t('Every ATIO property a mapping targets exists.'),
        'severity' => RequirementSeverity::OK,
      ]];
    }
    $predicates = array_unique(array_map(
      static fn($mapping): string => (string) $mapping->get('field_mapping_target_predicate')->value,
      $orphans,
    ));
    return ['sti_rdf_mapping_targets' => [
      'title' => $this->t('SKOS mappings to ATIO'),
      'value' => $this->t('@n mapping(s) target an ATIO property that no longer exists.', ['@n' => count($orphans)]),
      'description' => $this->t('No field of atio_innovation emits these predicates any more: @list. Restore the predicate in the rdf_sync mapping of atio_innovation, or remove these mappings and propose them again against the new property.', ['@list' => implode(', ', $predicates)]),
      'severity' => RequirementSeverity::Warning,
    ]];
  }

}
