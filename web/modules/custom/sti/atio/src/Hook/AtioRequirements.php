<?php

declare(strict_types=1);

namespace Drupal\atio\Hook;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Field\FieldConfigInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Compares atio_innovation's categorical properties with its facets.
 *
 * Both are hand-maintained lists over the same fields: the rdf_sync mapping
 * (which ATIO property a field is in the graph) and one facet config per
 * field. A field in one list but not the other is reported, as information:
 * it may be deliberate.
 */
final class AtioRequirements {

  use StringTranslationTrait;

  private const BUNDLE = 'atio_innovation';

  private const ATIO = 'https://sti-portal.fao.org/atio#';

  private const FACET_SOURCE = 'search_api_field:meta_entity.collection_settings.listing_content';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    $categorical = $this->categoricalFields();
    $faceted = $this->facetedAtioFields();
    $noFacet = array_diff($categorical, $faceted);
    $noPredicate = array_diff($faceted, $categorical);

    $value = $noFacet || $noPredicate
      ? $this->t('@a categorical ATIO field(s) without a facet; @b facet(s) on fields without an ATIO property.', ['@a' => count($noFacet), '@b' => count($noPredicate)])
      : $this->t('Every categorical ATIO field has a facet, and every ATIO facet has an ATIO property.');
    $description = [];
    if ($noFacet) {
      $description[] = $this->t('No facet: @list.', ['@list' => implode(', ', $noFacet)]);
    }
    if ($noPredicate) {
      $description[] = $this->t('No atio: predicate: @list.', ['@list' => implode(', ', $noPredicate)]);
    }
    return ['atio_facets' => [
      'title' => $this->t('ATIO facets'),
      'value' => $value,
      'description' => implode(' ', $description),
      'severity' => $noFacet || $noPredicate ? RequirementSeverity::Info : RequirementSeverity::OK,
    ]];
  }

  /**
   * Returns the fields that reference terms and emit an atio: predicate.
   *
   * @return string[]
   *   Field names.
   */
  private function categoricalFields(): array {
    $type = $this->entityTypeManager->getStorage('node_type')->load(self::BUNDLE);
    if ($type === NULL) {
      return [];
    }
    $definitions = $this->entityFieldManager->getFieldDefinitions('node', self::BUNDLE);
    // rdf_sync keeps a mapping on the bundle (keyed by field name) or on the
    // field config itself.
    $mapped = (array) $type->getThirdPartySetting('rdf_sync', 'fields', []);
    foreach ($definitions as $name => $definition) {
      if ($definition instanceof FieldConfigInterface && $definition->getThirdPartySettings('rdf_sync')) {
        $mapped[$name] = $definition->getThirdPartySettings('rdf_sync');
      }
    }
    $out = [];
    foreach ($mapped as $name => $columns) {
      $definition = $definitions[$name] ?? NULL;
      $predicate = (string) ($columns['target_id']['predicate'] ?? '');
      if ($definition !== NULL
        && str_starts_with($predicate, self::ATIO)
        && $definition->getType() === 'entity_reference'
        && $definition->getSetting('target_type') === 'taxonomy_term') {
        $out[] = (string) $name;
      }
    }
    sort($out);
    return $out;
  }

  /**
   * Returns the atio_innovation fields the listing facets are built on.
   *
   * @return string[]
   *   Field names.
   */
  private function facetedAtioFields(): array {
    if (!$this->entityTypeManager->hasDefinition('facets_facet')) {
      return [];
    }
    $fields = $this->entityFieldManager->getFieldDefinitions('node', self::BUNDLE);
    $out = [];
    foreach ($this->entityTypeManager->getStorage('facets_facet')->loadByProperties(['facet_source_id' => self::FACET_SOURCE]) as $facet) {
      $field = (string) $facet->getFieldIdentifier();
      // Only facets on atio_innovation's own fields; the listing also
      // carries facets for other bundles (claims, for instance).
      if (isset($fields[$field]) && str_starts_with($field, 'atio_')) {
        $out[] = $field;
      }
    }
    sort($out);
    return array_values(array_unique($out));
  }

}
