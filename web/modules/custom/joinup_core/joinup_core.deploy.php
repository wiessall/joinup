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

use Drupal\Core\Entity\EntityInterface;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Change 'culture' to 'culture and environment'.
 */
function joinup_core_deploy_109500(): string {
  // Update the references.
  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $vocab_manager->updateDbReference('http://joinup.eu/ontology/topic#culture', 'http://joinup.eu/ontology/topic#culture-and-education');
  return 'Updated the topic vocabulary.';
}

/**
 * Clean up cached_computed_field_expired_fields queue.
 */
function joinup_core_deploy_109501(): void {
  \Drupal::queue('cached_computed_field_expired_fields')->deleteQueue();
}

/**
 * Migrate IOP custom pages row paragraphs to layout paragraphs.
 */
function joinup_core_deploy_109502(): string {
  $collection = Rdf::load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);
  $group_helper = \Drupal::getContainer()->get('og.membership_manager');
  $node_ids = $group_helper->getGroupContentIds($collection, ['node']);

  foreach (Node::loadMultiple($node_ids['node']) as $node) {
    if ($node->bundle() !== 'custom_page') {
      continue;
    }

    $new_paragraphs = [];
    foreach ($node->field_paragraphs_body as $paragraph_row_item) {
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

    $node->get('field_paragraphs_body')->setValue(array_values($new_paragraphs));
    $node->save();
  }
  return "Migrated all custom pages row paragraphs of IOP collection to layout paragraphs.";
}

/**
 * Migrate Topic meta entity row paragraphs to layout paragraphs.
 */
function joinup_core_deploy_109503(array &$sandbox): string {
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
 * @param \Drupal\Core\Entity\EntityInterface $parent_entity
 *   The entity to use as parent.
 * @param string $parent_entity_field
 *   The field name of the parent entity.
 *
 * @return \Drupal\paragraphs\ParagraphInterface[]
 *   A list of created paragraphs.
 */
function _joinup_core_row_to_layout(ParagraphInterface $paragraph_row_item, EntityInterface $parent_entity, string $parent_entity_field): array {
  // The returned paragraphs will be assigned to the entity.
  $return = [];
  if (!empty($paragraph_row_item->paragraph_heading->value) || !empty($paragraph_row_item->paragraph_row_subheading->value)) {
    $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($parent_entity, $parent_entity_field, 'layout_onecol', NULL, $paragraph_row_item->get('row_theme')->value);
    $return[$new_layout_paragraph->id()] = $new_layout_paragraph;
    if ($heading = $paragraph_row_item->paragraph_heading->value) {
      $child_paragraph = _joinup_core_create_heading_paragraph($new_layout_paragraph, $heading, 'h2');
      $return[$child_paragraph->id()] = $child_paragraph;
    }
    if ($subheading = $paragraph_row_item->paragraph_row_subheading->value) {
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
    'type' => 'simple_paragraph',
    'field_body' => [
      'value' => "<{$heading_type}>" . $heading . "</{$heading_type}>",
      'format' => 'content_editor',
    ],
  ]);
  $child_paragraph->setBehaviorSettings('layout_paragraphs', [
    'parent_uuid' => $new_layout_paragraph->uuid(),
    'region' => $region,
  ]);
  $child_paragraph->save();
  return $child_paragraph;
}
