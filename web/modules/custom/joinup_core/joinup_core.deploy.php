<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup_media\Plugin\QueueWorker\MigrateMedia;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\search_api\Entity\Index;

/**
 * Clean up banner files.
 */
function joinup_core_deploy_109600(array &$sandbox): string {
  if (empty($sandbox['fids'])) {
    $query = <<<SPARQL
select ?o
from <http://joinup.eu/collection/published>
from <http://joinup.eu/collection/draft>
from <http://joinup.eu/solution/published>
from <http://joinup.eu/solution/draft>
from <http://joinup.eu/asset_release/published>
from <http://joinup.eu/asset_release/draft>
where { ?s <http://xmlns.com/foaf/0.1/Image> ?o }
SPARQL;

    $sparql = \Drupal::getContainer()->get('sparql.endpoint');
    $results = $sparql->query($query);
    foreach ($results as $result) {
      $sandbox['fids'][] = $result->o->getValue();
    }
    $sandbox['total'] = count($sandbox['fids']);
    $sandbox['current'] = 0;
  }

  $fids = array_splice($sandbox['fids'], 0, 100);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_storage->delete($file_storage->loadMultiple($fids));

  $sandbox['current'] += count($fids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return 'Processed ' . $sandbox['current'] . ' of ' . $sandbox['total'] . ' files.';
}

/**
 * Clean up banner database entries.
 */
function joinup_core_deploy_109601(): void {
  $sparql_endpoint = \Drupal::getContainer()->get('sparql.endpoint');
  $query = <<<SPARQL
DELETE { GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } }
WHERE {
  GRAPH ?g { ?s <http://xmlns.com/foaf/0.1/Image> ?o } .
  VALUES ?g { <http://joinup.eu/collection/published>  <http://joinup.eu/collection/draft> <http://joinup.eu/solution/published> <http://joinup.eu/solution/draft> <http://joinup.eu/asset_release/published> <http://joinup.eu/asset_release/draft> }
}
SPARQL;

  $sparql_endpoint->update($query);
}

/**
 * Update the topics list.
 */
function joinup_core_deploy_109602(): string {
  $vocabulary_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $messages = [];

  // The former Innovation must be renamed to 'Digital Innovation' and keep the
  // linked content items.
  $vocabulary_manager->renameTopic('http://joinup.eu/ontology/topic#innovation', 'Digital innovation');
  $messages[] = 'Topic "Innovation" renamed to "Digital innovation".';
  $vocabulary_manager->changeTopicId('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $vocabulary_manager->updateDbReference('http://joinup.eu/ontology/topic#innovation', 'http://joinup.eu/ontology/topic#digital-innovation');
  $messages[] = 'Topic ID "http://joinup.eu/ontology/topic#innovation" changed to "http://joinup.eu/ontology/topic#digital-innovation".';

  // A new parent category must be created, named 'Innovation'.
  $vocabulary_manager->createTopicTerm('Innovation');
  $messages[] = 'Topic category "Innovation" created.';

  // Under the new parent category, the following topics must be moved:
  // * Artificial Intelligence (move it by ICT parent topic).
  // * Blockchain (move it by ICT parent topic).
  // * 'Digital Innovation' (former Innovation).
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#artificial-intelligence', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#blockchain', 'http://joinup.eu/ontology/topic/category#innovation');
  $vocabulary_manager->changeTopicParent('http://joinup.eu/ontology/topic#digital-innovation', 'http://joinup.eu/ontology/topic/category#innovation');
  $messages[] = 'Topics "Artificial Intelligence", "Blockchain" and "Digital Innovation" moved to "Innovation" category.';

  // Under the new parent category, the following topics must be added:
  // * Advanced data analytics
  // * AI Chatbot
  // * AI Facial recognition
  // * AI Image generation
  // * AI Machine translation
  // * AI Named entity recognition
  // * AI Speech recognition
  // * AI Video analytics
  // * Extended reality
  // * High performance computing
  // * Indoor position systems
  // * Internet of Things
  // * Optical Character Recognition
  // * Robotic Process Automation.
  $labels = [
    'Advanced data analytics',
    'AI Chatbot',
    'AI Facial recognition',
    'AI Image generation',
    'AI Machine translation',
    'AI Named entity recognition',
    'AI Speech recognition',
    'AI Video analytics',
    'Extended reality',
    'High performance computing',
    'Indoor position systems',
    'Internet of Things',
    'Optical Character Recognition',
    'Robotic Process Automation',
  ];

  foreach ($labels as $label) {
    $vocabulary_manager->createTopicTerm($label, 'http://joinup.eu/ontology/topic/category#innovation');
    $messages[] = "Topic \"$label\" created.";
  }

  return implode(PHP_EOL, $messages);
}

/**
 * Re-indexes items related to the affected changed terms.
 */
function joinup_core_deploy_109603(): string {
  $affected_terms = [
    'http://joinup.eu/ontology/topic/category#innovation',
    'http://joinup.eu/ontology/topic#innovation',
    'http://joinup.eu/ontology/topic#artificial-intelligence',
    'http://joinup.eu/ontology/topic#blockchain',
    'http://joinup.eu/ontology/topic#digital-innovation',
  ];

  $topic_ids = \Drupal::entityQuery('taxonomy_term')
    ->condition('vid', 'topic')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_map(function (string $id): string {
    return "entity:taxonomy_term/$id:en";
  }, $topic_ids);

  $node_ids = \Drupal::entityQuery('node')
    ->condition('field_topic', $affected_terms, 'IN')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_merge($search_ids, array_map(function (string $id): string {
    return "entity:node/$id:en";
  }, $node_ids));

  $rdf_entity_ids = \Drupal::entityQuery('rdf_entity')
    ->condition('field_topic', $affected_terms, 'IN')
    ->accessCheck(FALSE)
    ->execute();
  $search_ids = array_merge($search_ids, array_map(function (string $id): string {
    return "entity:rdf_entity/$id:en";
  }, $rdf_entity_ids));

  $index = Index::load('published');
  $items = $index->loadItemsMultiple($search_ids);
  $index->indexSpecificItems($items);

  return 'Items re-indexed.';
}

/**
 * Migrate IOP custom pages row paragraphs to layout paragraphs.
 */
function joinup_core_deploy_109604(): string {
  $collection = Rdf::load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);
  $group_helper = \Drupal::getContainer()->get('og.membership_manager');
  $node_ids = $group_helper->getGroupContentIds($collection, ['node']);

  foreach (Node::loadMultiple($node_ids['node']) as $node) {
    if ($node->bundle() !== 'custom_page') {
      continue;
    }

    $new_paragraphs = [];
    foreach ($node->get('field_paragraphs_body') as $paragraph_row_item) {
      // The $paragraph_row_item is a field item that holds a row. If it is not
      // a row paragraph, ignore it.
      $paragraph_row_item = $paragraph_row_item->entity;
      if ($paragraph_row_item->bundle() !== 'row') {
        continue;
      }

      $new_paragraphs += _joinup_core_row_to_layout($paragraph_row_item, $node, 'field_paragraphs_body');
    }

    if (empty($new_paragraphs)) {
      continue;
    }

    $node->set('field_paragraphs_body', array_values($new_paragraphs));
    $node->save();
  }
  return "Migrated all custom pages row paragraphs of IOP collection to layout paragraphs.";
}

/**
 * Migrate Topic meta entity row paragraphs to layout paragraphs.
 */
function joinup_core_deploy_109605(array &$sandbox): string {
  if (empty($sandbox['meta_ids'])) {
    $sandbox['meta_ids'] = \Drupal::entityQuery('meta_entity')
      ->accessCheck(FALSE)
      ->condition('type', 'topic')
      ->execute();
  }
  // Only update 1 entity per batch.
  $meta_entities = MetaEntity::loadMultiple(array_splice($sandbox['meta_ids'], 0, 1));
  $meta_entity = reset($meta_entities);

  $field = 'topics_paragraph';
  $new_paragraphs = [];
  foreach ($meta_entity->{$field} as $paragraph_row_item) {
    // The $paragraph_row_item is a field item that holds a row. If it is not
    // a row paragraph, ignore it.
    $paragraph_row_item = $paragraph_row_item->entity;
    if ($paragraph_row_item->bundle() !== 'row') {
      continue;
    }

    $new_paragraphs += _joinup_core_row_to_layout($paragraph_row_item, $meta_entity, $field);
  }

  if (!empty($new_paragraphs)) {
    $meta_entity->get($field)->setValue(array_values($new_paragraphs));
    $meta_entity->save();
  }

  $sandbox['#finished'] = empty($sandbox['meta_ids']) ? 1 : 0;
  return "Migrated meta entity '{$meta_entity->label()}' row paragraphs to layout paragraphs.";
}

/**
 * Migrate a row to the new layout.
 *
 * @param \Drupal\paragraphs\ParagraphInterface $paragraph_row_item
 *   The row paragraph.
 * @param \Drupal\Core\Entity\ContentEntityInterface $parent_entity
 *   The entity to use as parent.
 * @param string $parent_entity_field
 *   The field name of the parent entity.
 *
 * @return \Drupal\paragraphs\ParagraphInterface[]
 *   A list of created paragraphs.
 */
function _joinup_core_row_to_layout(ParagraphInterface $paragraph_row_item, ContentEntityInterface $parent_entity, string $parent_entity_field): array {
  // The returned paragraphs will be assigned to the entity.
  $return = [];
  $heading = $paragraph_row_item->paragraph_heading->value ?? '';
  $subheading = $paragraph_row_item->paragraph_row_subheading->value ?? '';

  // Exclude the "Did you know?" paragraph because it needs some special care.
  if ($heading !== 'Did you know?' && (!empty($heading) || !empty($subheading))) {
    $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_onecol', NULL, $paragraph_row_item->get('row_theme')->value);
    $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
    if ($heading) {
      $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $heading, 'h2');
      $return[$child_paragraph->id()] = $child_paragraph;
    }
    if ($subheading) {
      $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $subheading, 'h3');
      $return[$child_paragraph->id()] = $child_paragraph;
    }
  }

  // Start migrating paragraphs to a new list. Keep the deepest levels of
  // paragraphs that can be reused and recreate the layout and the columns.
  // Iterate through the paragraphs within the row. For each sub-paragraph,
  // that is of type `1_column_layout`, `2_column_layout`,
  // `3_column_layout`, `2_column_featured_right` or
  // `2_column_featured_left`, create a new layout paragraph and add it to
  // the list of new paragraphs. The field in the row paragraph that holds
  // the sub-paragraphs is `paragraph_column_layout`.
  foreach ($paragraph_row_item->paragraph_column_layout as $paragraph_column_item) {
    // The $paragraph_column_item is a field item that holds a column. If it
    // is not a column paragraph, ignore it.
    $column_paragraph = $paragraph_column_item->entity;
    if (!in_array($column_paragraph->bundle(), [
      '1_column_layout',
      '2_column_layout',
      '3_column_layout',
      '2_column_featured_right',
      '2_column_featured_left',
    ])) {
      continue;
    }

    // The way hierarchy works in layouts is that the layout paragraph has
    // the 'behavior_settings' base field where the 'layout_paragraphs'
    // setting is an array of settings in it. The 'layout_paragraphs'
    // setting has the following structure for the layout:
    // - 'layout' = 'layout_onecol', 'layout_twocol_section',
    // 'layout_threecol_section' or 'layout_fourcol_section'.
    // - 'config' => ['column_widths' => '50-50' for twocol, '33-33-33' for
    // threecol, '25-25-25-25' for fourcol or empty for onecol]
    // - 'parent_uuid' = NULL
    // - 'region' = NULL
    // - background_theme = whatever the paragraph we copy from has.
    // The 'layout_paragraphs' setting has the following structure for the
    // column (any paragraph belonging to it):
    // - 'parent_uuid' = UUID of the layout paragraph.
    // - 'region' = 'first', 'second', 'third' or 'fourth'.
    switch ($column_paragraph->bundle()) {
      case '1_column_layout':
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_onecol', NULL, $paragraph_row_item->row_theme->value);
        $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
        if ($heading === 'Did you know?') {
          // Handle the special case of the 'Did you know?' row.
          $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $heading, 'h2');
          $return[$child_paragraph->id()] = $child_paragraph;
          $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $subheading, 'h3');
          $return[$child_paragraph->id()] = $child_paragraph;
          $new_layout_paragraph->setBehaviorSettings('extra_class', ['extra_class' => 'facts']);
          $new_layout_paragraph->save();
        }
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_center', 'content');
        break;

      case '2_column_layout':
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_twocol_section', '50-50', $paragraph_row_item->row_theme->value);
        $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_left', 'first');
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_right', 'second');
        break;

      case '3_column_layout':
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_threecol_section', '33-33-33', $paragraph_row_item->row_theme->value);
        $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_left', 'first');
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_center', 'second');
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'paragraph_column_right', 'third');
        break;

      // The 2 column featured paragraphs are a special case. They are
      // converted to a layout with 2 columns, but the left column is
      // configured to be a featured column. To make a column featured, we
      // need to add a nested layout to it.
      case '2_column_featured_right':
      case '2_column_featured_left':
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_twocol_section', '50-50', $paragraph_row_item->row_theme->value, 'featured-' . $column_paragraph->bundle());
        $non_featured_region = $column_paragraph->bundle() === '2_column_featured_right' ? 'first' : 'second';
        $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
        // Featured paragraphs had an explicit heading field for the
        // non-featured column. We need to convert that to a paragraph.
        if ($heading = $column_paragraph->paragraph_heading->value) {
          $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $heading, 'h2', $non_featured_region);
          $return[$child_paragraph->id()] = $child_paragraph;
        }
        $return += _joinup_core_column_to_layout($column_paragraph, $new_layout_paragraph, $parent_entity, $parent_entity_field, 'field_paragraphs_item_content', $non_featured_region);

        // The featured column has a single paragraph to it. Get the first
        // paragraph and add it to the new layout on the second column.
        $featured_paragraph = $column_paragraph->get('paragraph_2cf_media')->first()->entity;
        $featured_paragraph->set('parent_field_name', $parent_entity_field);
        $featured_paragraph->setParentEntity($parent_entity, $parent_entity_field);
        $featured_paragraph->setBehaviorSettings('layout_paragraphs', [
          'parent_uuid' => $new_layout_paragraph->uuid(),
          // Place it in the featured region.
          'region' => $non_featured_region === 'first' ? 'second' : 'first',
        ]);
        $featured_paragraph->save();
        $return[$featured_paragraph->id()] = $featured_paragraph;
        break;

      default:
        break;
    }
    $column_paragraph->delete();
    $paragraph_row_item->delete();
  }

  return $return;
}

/**
 * Returns a serialized version of the behavior settings for a paragraph.
 *
 * @param \Drupal\Core\Entity\EntityInterface $parent_entity
 *   The node to use as parent.
 * @param string $parent_entity_field
 *   The field name on the parent entity.
 * @param string $layout
 *   The layout to use. One of 'layout_onecol', 'layout_twocol',
 *   'layout_threecol'.
 * @param string|null $column_widths
 *   The column widths to use. E.g. '33-33-33' or '50-50'.
 * @param string|null $background_theme
 *   (optional) The background theme to use. Defaults to NULL. NULL means no
 *   background theme or behavior not available.
 * @param string|null $extra_classes
 *   (optional) Extra classes to use. Defaults to NULL. NULL means no extra
 *   classes or behavior not available.
 *
 * @return \Drupal\paragraphs\ParagraphInterface
 *   The generated paragraph.
 */
function _joinup_core_get_new_layout_paragraph(EntityInterface $parent_entity, string $parent_entity_field, string $layout, ?string $column_widths = NULL, ?string $background_theme = NULL, ?string $extra_classes = NULL): ParagraphInterface {
  $behavior = [
    'layout_paragraphs' => [
      'layout' => $layout,
      'config' => [
        'label' => '',
      ],
      'parent_uuid' => NULL,
      'region' => NULL,
    ],
  ];

  if ($column_widths) {
    $behavior['config']['column_widths'] = $column_widths;
  }

  if ($background_theme === 'light') {
    $behavior['background_theme'] = [
      'background_theme' => '',
    ];
  }

  elseif ($background_theme === 'light-wave') {
    $behavior['background_theme'] = [
      'background_theme' => 'light',
    ];
  }

  if ($extra_classes === 'featured-2_column_featured_right') {
    $behavior['extra_class'] = [
      'extra_class' => 'banner',
    ];
  }
  elseif ($extra_classes === 'featured-2_column_featured_left') {
    $behavior['extra_class'] = [
      'extra_class' => 'why',
    ];
  }

  $behavior = serialize($behavior);
  $new_layout_paragraph = Paragraph::create([
    'type' => 'layout',
    'parent_type' => 'paragraph',
    'behavior_settings' => $behavior,
  ]);
  $new_layout_paragraph->setParentEntity($parent_entity, $parent_entity_field);
  $new_layout_paragraph->save();
  return $new_layout_paragraph;
}

/**
 * Migrate a list of column paragraphs to the new layout.
 *
 * @param \Drupal\paragraphs\ParagraphInterface $old_parent_column
 *   The old parent column paragraph.
 * @param \Drupal\paragraphs\ParagraphInterface $new_parent_layout
 *   The new parent layout paragraph.
 * @param \Drupal\Core\Entity\EntityInterface $parent_entity
 *   The entity to use as parent.
 * @param string $parent_entity_field
 *   The field name of the parent entity.
 * @param string $paragraphs_reference_field_name
 *   The field name of the column paragraphs.
 * @param string $new_region
 *   The region to use for the new paragraphs.
 *
 * @return \Drupal\paragraphs\ParagraphInterface[]
 *   A list of created paragraphs.
 */
function _joinup_core_column_to_layout(ParagraphInterface $old_parent_column, ParagraphInterface $new_parent_layout, EntityInterface $parent_entity, string $parent_entity_field, string $paragraphs_reference_field_name, string $new_region): array {
  $return = [];
  foreach ($old_parent_column->get($paragraphs_reference_field_name) as $child_paragraph_item) {
    $child_paragraph = $child_paragraph_item->entity;
    $child_paragraph->setParentEntity($parent_entity, $parent_entity_field);
    $child_paragraph->setBehaviorSettings('layout_paragraphs', [
      'parent_uuid' => $new_parent_layout->uuid(),
      'region' => $new_region,
    ]);
    $child_paragraph->save();
    $return[$child_paragraph->id()] = $child_paragraph;
  }

  return $return;
}

/**
 * Creates a heading paragraph as a simple paragraph.
 *
 * @param \Drupal\paragraphs\ParagraphInterface $new_layout_paragraph
 *   The layout paragraph to add the heading to.
 * @param string $heading
 *   The heading text.
 * @param string $heading_type
 *   The heading type, e.g. h2, h3, etc.
 * @param string $region
 *   (optional) The region to add the heading to. Defaults to 'content'.
 */
function _joinup_core_create_heading_paragraph(ParagraphInterface $new_layout_paragraph, string $heading, string $heading_type, string $region = 'content'): ParagraphInterface {
  $child_paragraph = Paragraph::create([
    'type' => 'heading',
    'paragraph_heading' => [
      'value' => "$heading",
    ],
  ]);
  $child_paragraph->setBehaviorSettings('layout_paragraphs', [
    'parent_uuid' => $new_layout_paragraph->uuid(),
    'region' => $region,
  ]);
  $child_paragraph->setBehaviorSettings('paragraph_heading', [
    'heading_type' => $heading_type,
  ]);
  $child_paragraph->save();
  return $child_paragraph;
}

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109606(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');
  $db = \Drupal::database();

  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'video')
      ->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }

  $nids = array_splice($sandbox['ids'], 0, 50);
  /** @var \Drupal\joinup_video\Entity\VideoInterface $video */
  foreach ($node_storage->loadMultiple($nids) as $video) {
    if ($url = $video->get('field_video')->value) {
      $media = MigrateMedia::getMedia('video', $url, (int) $video->getOwnerId(), $video->get('og_audience')->target_id);
      $options = [
        'bundle' => 'video',
        'entity_id' => $video->id(),
        'revision_id' => $video->getRevisionId(),
        'langcode' => 'en',
        'delta' => 0,
        'video_target_id' => $media->id(),
      ];
      $db->insert('node_revision__video')->fields($options)->execute();
      $db->insert('node__video')->fields($options)->execute();
    }
    $sandbox['count']++;
  }
  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];

  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed fields.";
}

/**
 * Migrate videos and images embed markup into media.
 */
function joinup_core_deploy_109607(array &$sandbox): string {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $db = \Drupal::database();
  $queue = \Drupal::queue('joinup_media_migrate');

  if (empty($sandbox['tasks'])) {
    $sql = <<<QUERY
      SELECT DISTINCT b.entity_id, g.og_audience_target_id, n.uid
      FROM {node_revision__body} b
      INNER JOIN {node__og_audience} g ON b.entity_id = g.entity_id
      INNER JOIN {node_field_data} n ON b.entity_id = n.nid
      WHERE b.deleted = 0
      AND (b.body_value LIKE '%preview_thumbnail%' OR b.body_value LIKE '%<img%data-entity-type="file"%')
      QUERY;
    $sandbox['tasks']['node'] = array_map(function (array $item): array {
      return [(int) $item[0], $item[1], (int) $item[2]];
    }, $db->query($sql)->fetchAll(\PDO::FETCH_NUM));

    // The most relevant part in this query is getting the topmost node ID as we
    // need the node's group ID and owner but the paragraphs are nested.
    // @see https://dba.stackexchange.com/questions/291321/get-the-highest-parent-id-recursively-in-mysql
    $sql = <<<QUERY
      WITH RECURSIVE cte AS (
        SELECT id, type, parent_id, parent_id AS topmost_nid, revision_id
          FROM {paragraphs_item_field_data}
          WHERE parent_type = 'node'
        UNION ALL
        SELECT t.id, t.type, t.parent_id, cte.topmost_nid, t.revision_id
          FROM {paragraphs_item_field_data} t
          INNER JOIN cte ON t.parent_id = cte.id
      )
      SELECT DISTINCT r.parent_id, o.og_audience_target_id, n.uid
        FROM cte
        INNER JOIN {node__og_audience} o ON cte.topmost_nid = o.entity_id
        INNER JOIN {node_field_data} n ON cte.topmost_nid = n.nid
        LEFT JOIN {paragraphs_item_revision_field_data} r ON cte.id = r.id
        LEFT JOIN {paragraph_revision__field_body} b ON r.revision_id = b.revision_id
        WHERE cte.type = 'simple_paragraph'
        AND (b.field_body_value LIKE '%preview_thumbnail%' OR b.field_body_value LIKE '%<img%data-entity-type="file"%')
      QUERY;
    $sandbox['tasks']['paragraph'] = array_map(function (array $item): array {
      return [(int) $item[0], $item[1], (int) $item[2]];
    }, $db->query($sql)->fetchAll(\PDO::FETCH_NUM));

    $sparql_query = <<<QUERY
      SELECT ?graph ?entityId
      FROM NAMED <http://joinup.eu/collection/published>
      FROM NAMED <http://joinup.eu/collection/draft>
      FROM NAMED <http://joinup.eu/solution/published>
      FROM NAMED <http://joinup.eu/solution/draft>
      FROM NAMED <http://joinup.eu/asset_release/published>
      FROM NAMED <http://joinup.eu/asset_release/draft>
      FROM NAMED <http://joinup.eu/asset_distribution/published>
      FROM NAMED <http://joinup.eu/asset_distribution/draft>
      WHERE {
        GRAPH ?graph {
          ?entityId ?predicate ?value .
          FILTER(?predicate IN (<http://www.w3.org/ns/adms#versionNotes>, <http://purl.org/dc/terms/description>)) .
          FILTER(REGEX(STR(?value), 'preview_thumbnail|data-entity-type="file"', "i")) .
        }
      }
      QUERY;
    foreach ($sparql->query($sparql_query) as $row) {
      $graph_id = str_ends_with((string) $row->graph, '/draft') ? 'draft' : 'default';
      $sandbox['tasks']['rdf_entity'][] = [$graph_id, (string) $row->entityId];
    }

    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['tasks']['node']) + count($sandbox['tasks']['paragraph']) + count($sandbox['tasks']['rdf_entity']);
  }

  $type = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'][$type], 0, 100);
  if (empty($sandbox['tasks'][$type])) {
    unset($sandbox['tasks'][$type]);
  }

  foreach ($tasks as $task) {
    $item = [
      'type' => $type,
      'task' => $task,
    ];
    $queue->createItem($item);
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Queued {$sandbox['count']} out of {$sandbox['max']} text blobs.";
}
