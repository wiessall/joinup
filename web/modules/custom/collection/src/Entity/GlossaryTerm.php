<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\node\Entity\Node;
use Drupal\text\Plugin\Field\FieldType\TextWithSummaryItem;

/**
 * Entity subclass for the 'collection' bundle.
 */
class GlossaryTerm extends Node implements GlossaryTermInterface {

  use GlossaryTermTrait;
  use NodeCollectionContentTrait;

  /**
   * {@inheritdoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  public static function create(array $values = []): GlossaryTermInterface {
    // Delegate to the parent method. This is only overridden to provide the
    // correct return type.
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getSynonyms(): array {
    return array_map(function (array $field_item): string {
      return $field_item['value'];
    }, $this->get('field_glossary_synonyms')->getValue());
  }

  /**
   * {@inheritdoc}
   */
  public function getSummary(): string {
    $field_item = $this->getFirstItem('field_glossary_definition');

    // The definition is a required field, so it should normally be present on
    // the entity. If not, this is probably a new, unpopulated entity.
    if (!$field_item instanceof TextWithSummaryItem || $field_item->isEmpty()) {
      return '';
    }

    return $this->buildSummary($field_item->value, $field_item->summary, $field_item->format);
  }

}
