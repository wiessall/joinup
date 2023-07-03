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

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Change 'culture' to 'culture and environment'.
 */
function joinup_core_deploy_109400(): string {
  // Update the references.
  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $vocab_manager->updateDbReference('http://joinup.eu/ontology/topic#culture', 'http://joinup.eu/ontology/topic#culture-and-education');
  return 'Updated the topic vocabulary.';
}

/**
 * Clean up cached_computed_field_expired_fields queue.
 */
function joinup_core_deploy_109401(): void {
  \Drupal::queue('cached_computed_field_expired_fields')->deleteQueue();
}

/**
 * Migrate IOP custom pages to paragraphs.
 */
function joinup_core_deploy_109402(): string {
  $collection = Rdf::load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);
  $group_helper = \Drupal::getContainer()->get('og.membership_manager');
  $node_ids = $group_helper->getGroupContentIds($collection, ['node']);
  $paragraphs_cleanup = [];

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

      if ($heading = $paragraph_row_item->paragraph_heading->value) {
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_onecol', NULL, $paragraph_row_item->get('row_theme')->value);
        $new_paragraphs[] = $new_layout_paragraph;
        // Create a new paragraph of type 'simple_paragraph' where the
        // 'field_body' field will contain the heading as h2.
        $child_paragraph = Paragraph::create([
          'type' => 'simple_paragraph',
          'field_body' => [
            'value' => "<h2>" . $heading . "</h2>",
            'format' => 'content_editor',
          ],
        ]);
        $child_paragraph->setBehaviorSettings('layout_paragraphs', [
          'parent_uuid' => $new_layout_paragraph->uuid(),
          'region' => 'content',
        ]);
        $child_paragraph->save();
        $new_paragraphs[] = $child_paragraph;
      }

      if ($subheading = $paragraph_row_item->paragraph_row_subheading->value) {
        $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_onecol', NULL, $paragraph_row_item->get('row_theme')->value);
        $new_paragraphs[] = $new_layout_paragraph;
        // Create a new paragraph of type 'simple_paragraph' where the
        // 'field_body' field will contain the subheading as h3.
        $child_paragraph = Paragraph::create([
          'type' => 'simple_paragraph',
          'field_body' => [
            'value' => "<h3>" . $subheading . "</h3>",
            'format' => 'content_editor',
          ],
        ]);
        $child_paragraph->setBehaviorSettings('layout_paragraphs', [
          'parent_uuid' => $new_layout_paragraph->uuid(),
          'region' => 'content',
        ]);
        $child_paragraph->save();
        $new_paragraphs[] = $child_paragraph;
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
            $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_onecol', NULL, $paragraph_row_item->get('row_theme')->value);
            $new_paragraphs[] = $new_layout_paragraph;

            // This is the simplest case. Convert all child paragraphs of the
            // single column paragraphs, to have the new layout as a parent and
            // be configured to the first region.
            foreach ($column_paragraph->paragraph_column_center as $child_paragraph_item) {
              $child_paragraph = $child_paragraph_item->entity;
              $child_paragraph->setParentEntity($node, 'field_paragraphs_body');
              $child_paragraph->setBehaviorSettings('layout_paragraphs', [
                'parent_uuid' => $new_layout_paragraph->uuid(),
                'region' => 'content',
              ]);
              $child_paragraph->save();
              $new_paragraphs[] = $child_paragraph;
            }
            $paragraphs_cleanup[$column_paragraph->id()] = $column_paragraph;
            break;

          case '2_column_layout':
            $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_twocol_section', '50-50', $paragraph_row_item->get('row_theme')->value);
            $new_paragraphs[] = $new_layout_paragraph;
            $fields = [
              'paragraph_column_left',
              'paragraph_column_right',
            ];

            foreach ($fields as $field) {
              foreach ($column_paragraph->get($field) as $child_paragraph_item) {
                /** @var \Drupal\paragraphs\ParagraphInterface $child_paragraph */
                $child_paragraph = $child_paragraph_item->entity;
                $non_featured_region = $field === 'paragraph_column_left' ? 'first' : 'second';
                $child_paragraph->setParentEntity($node, 'field_paragraphs_body');
                $child_paragraph->setBehaviorSettings('layout_paragraphs', [
                  'parent_uuid' => $new_layout_paragraph->uuid(),
                  'region' => $non_featured_region,
                ]);
                $child_paragraph->save();
                $new_paragraphs[] = $child_paragraph;
              }
              $paragraphs_cleanup[$column_paragraph->id()] = $column_paragraph;
            }
            break;

          case '3_column_layout':
            $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_threecol_section', '33-33-33', $paragraph_row_item->get('row_theme')->value);
            $new_paragraphs[] = $new_layout_paragraph;

            $fields = [
              'paragraph_column_left',
              'paragraph_column_center',
              'paragraph_column_right',
            ];

            foreach ($fields as $field) {
              if ($field === 'paragraph_column_center') {
                $non_featured_region = 'second';
              }
              else {
                $non_featured_region = $field === 'paragraph_column_left' ? 'first' : 'third';
              }
              foreach ($column_paragraph->get($field) as $child_paragraph_item) {
                $child_paragraph = $child_paragraph_item->entity;
                $child_paragraph->setParentEntity($node, 'field_paragraphs_body');
                $child_paragraph->setBehaviorSettings('layout_paragraphs', [
                  'parent_uuid' => $new_layout_paragraph->uuid(),
                  'region' => $non_featured_region,
                ]);
                $child_paragraph->save();
                $new_paragraphs[] = $child_paragraph;
              }
              $paragraphs_cleanup[$column_paragraph->id()] = $column_paragraph;
            }

            break;

          // The 2 column featured paragraphs are a special case. They are
          // converted to a layout with 2 columns, but the left column is
          // configured to be a featured column. To make a column featured, we
          // need to add a nested layout to it.
          case '2_column_featured_right':
          case '2_column_featured_left':
            $new_layout_paragraph = _joinup_core_get_new_layout_paragraph($node, 'layout_twocol_section', '50-50', $paragraph_row_item->get('row_theme')->value, 'featured-' . $column_paragraph->bundle());
            $non_featured_region = $column_paragraph->bundle() === '2_column_featured_right' ? 'first' : 'second';
            $new_paragraphs[] = $new_layout_paragraph;
            // Featured paragraphs had an explicit heading field for the non
            // featured column. We need to convert that to a paragraph.
            if ($heading = $column_paragraph->paragraph_heading->value) {
              $child_paragraph = Paragraph::create([
                'type' => 'simple_paragraph',
                'field_body' => [
                  'value' => "<h2>" . $heading . "</h2>",
                  'format' => 'content_editor',
                ],
              ]);
              $child_paragraph->setBehaviorSettings('layout_paragraphs', [
                'parent_uuid' => $new_layout_paragraph->uuid(),
                'region' => $non_featured_region,
              ]);
              $child_paragraph->save();
              $new_paragraphs[] = $child_paragraph;
            }

            // Handle first the normal column. The field is
            // field_paragraphs_item_content.
            foreach ($column_paragraph->get('field_paragraphs_item_content') as $child_paragraph_item) {
              /** @var \Drupal\paragraphs\ParagraphInterface $child_paragraph */
              $child_paragraph = $child_paragraph_item->entity;
              $child_paragraph->setParentEntity($node, 'field_paragraphs_body');
              $child_paragraph->setBehaviorSettings('layout_paragraphs', [
                'parent_uuid' => $new_layout_paragraph->uuid(),
                'region' => $non_featured_region,
              ]);
              $child_paragraph->save();
              $new_paragraphs[] = $child_paragraph;
            }
            $paragraphs_cleanup[$column_paragraph->id()] = $column_paragraph;

            // The featured column has a single paragraph to it. Get the first
            // paragraph and add it to the new layout on the second column.
            $featured_paragraph = $column_paragraph->get('paragraph_2cf_media')
              ->first()->entity;
            $featured_paragraph->set('parent_field_name', 'field_paragraphs_body');
            $featured_paragraph->setParentEntity($node, 'field_paragraphs_body');
            $featured_paragraph->setBehaviorSettings('layout_paragraphs', [
              'parent_uuid' => $new_layout_paragraph->uuid(),
              'region' => $non_featured_region === 'first' ? 'second' : 'first',
            ]);
            $featured_paragraph->save();
            $new_paragraphs[] = $featured_paragraph;
            break;

          default:
            break;
        }
        $paragraphs_cleanup[$paragraph_row_item->id()] = $paragraph_row_item;
      }
    }

    if (empty($new_paragraphs)) {
      continue;
    }

    $node->get('field_paragraphs_body')->setValue($new_paragraphs);
    $node->save();
  }

  // Delete the old paragraphs.
  foreach ($paragraphs_cleanup as $paragraph) {
    $paragraph->delete();
  }

  return "Updated all custom pages of IOP collection.";
}

/**
 * Returns a serialized version of the behavior settings for a paragraph.
 *
 * @param \Drupal\node\NodeInterface $node
 *   The node to use as parent.
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
function _joinup_core_get_new_layout_paragraph(NodeInterface $node, string $layout, ?string $column_widths = NULL, ?string $background_theme = NULL, ?string $extra_classes = NULL): ParagraphInterface {
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

  if ($background_theme) {
    $behavior['background_theme'] = [
      'background_theme' => $background_theme,
    ];
  }

  if ($extra_classes) {
    $behavior['extra_class'] = [
      'extra_class' => $extra_classes,
    ];
  }

  $behavior = serialize($behavior);
  $new_layout_paragraph = Paragraph::create([
    'type' => 'layout',
    'parent_type' => 'paragraph',
    'behavior_settings' => $behavior,
  ]);
  $new_layout_paragraph->setParentEntity($node, 'field_paragraphs_body');
  $new_layout_paragraph->save();
  return $new_layout_paragraph;
}
