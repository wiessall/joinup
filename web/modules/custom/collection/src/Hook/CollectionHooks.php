<?php

declare(strict_types=1);

namespace Drupal\collection\Hook;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Collection module hook implementations.
 */
class CollectionHooks {

  /**
   * Makes the query for glossary terms language aware.
   *
   * There's no way to determine the language of the data and dedicated tables
   * when writing an entity aggregate query, so we have to do it here.
   *
   * @param \Drupal\Core\Database\Query\SelectInterface $select
   *   The select query.
   *
   * @see \Drupal\collection\Plugin\Filter\Glossary::getReplacementsMap()
   */
  #[Hook('query_glossary_term_map_alter')]
  public function alterCollectionGlossaryTermMapQuery(SelectInterface $select): void {
    if ($select->hasAllTags('entity_query', 'entity_query_node')) {
      $or = $select->orConditionGroup()
        // If there is a current language translation, use it.
        ->condition('[node_field_data].[langcode]', $select->getMetaData('langcode'))
        // Otherwise, fallback to the glossary term original language.
        ->condition('[node_field_data].[langcode]', '[base_table].[langcode]');
      $select->condition($or);

      // Joined tables should filter by the same language as {node_field_data}.
      $tables =& $select->getTables();
      $tables['node_field_data_2']['condition'] .= ' AND [node_field_data_2].[langcode] = [node_field_data].[langcode]';
      $tables['node__field_glossary_definition']['condition'] .= ' AND [node__field_glossary_definition].[langcode] = [node_field_data].[langcode]';
      $tables['node__field_glossary_synonyms']['condition'] .= ' AND [node__field_glossary_synonyms].[langcode] = [node_field_data].[langcode]';
    }
  }

}
