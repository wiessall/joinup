<?php

/**
 * @file
 * Node specific procedural functions.
 */

declare(strict_types=1);

use Drupal\eif\EifInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_group\JoinupGroupHelper;

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__numbered_listing(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];

  // Trim the label text.
  $variables['label'] = _ventuno_smart_trim($node->getTitle(), 65);

  // Expose the logo if it exists.
  if ($node instanceof LogoInterface) {
    $field_name = $node->getLogoFieldName();
    if (!empty($variables['content'][$field_name])) {
      $variables['logo'] = $variables['content'][$field_name];
    }
  }

  // Provide date using the date format that is standardized site wide.
  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
  $date_formatter = \Drupal::service('date.formatter');
  $variables['date'] = $date_formatter->format($node->getCreatedTime(), 'date_only');

  // Provide the content type.
  $variables['type'] = $node->type->entity->label();
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_node__explore_item(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  // Trim the labels of the items in the Explore block on the homepage down to a
  // manageable size.
  $variables['label'] = _ventuno_smart_trim($node->getTitle(), 45);
}

/**
 * Implements hook_preprocess_node().
 *
 * - Adds the site URL to use in nodes.
 * - Adds the group ID and label as data attributes in tiles.
 * - Adds bundle class in listing templates for bundle specific theming.
 * - Adds the created date to news articles.
 * - Adds a variable indicating the attachment file type on documents.
 */
function ventuno_preprocess_node(array &$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  $bundle = $node->bundle();

  // Add the site URL to use in nodes.
  $variables['site_url'] = \Drupal::request()->getSchemeAndHttpHost();

  // Add the group ID and label as data attributes in tiles.
  if (in_array($variables['view_mode'], ['carousel', 'view_mode_tile'])) {
    /** @var \Drupal\rdf_entity\RdfInterface|null $parent */
    $parent = JoinupGroupHelper::getGroup($node);
    if ($parent) {
      $variables['attributes']['data-joinup-parent-id'] = $parent->id();
      $variables['attributes']['data-joinup-parent-label'] = $parent->label();
    }

    // If the node is shared somewhere, attach the related library.
    if ($node->hasField('field_shared_in') && !$node->get('field_shared_in')->isEmpty()) {
      $variables['#attached']['library'][] = 'ventuno/shared-entity';
    }
  }

  // View modes where tiles are expected.
  $tile_view_modes = [
    'highlighted',
    'view_mode_tile',
    'view_mode_tile_horizontal',
    'carousel',
  ];
  // Prepare data for the tiles.
  if (in_array($variables['view_mode'], $tile_view_modes)) {
    _ventuno_preprocess_tile($variables, 'node');
  }

  // Add the created date to news articles.
  if ($bundle === 'news') {
    $variables['created'] = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'date_only');
  }

  // Add a variable indicating the attachment file type on documents.
  if ($bundle === 'document' && !$node->get('field_file')->isEmpty()) {
    /** @var \Drupal\file\FileInterface $file */
    $file = $node->get('field_file')->first()->entity;

    // The document could reference an orphaned file entity, so only proceed if
    // we actually have a file.
    if (!empty($file)) {
      $is_remote = \Drupal::service('file_url.handler')->isRemote($file);

      if (!$is_remote) {
        $variables['extension'] = pathinfo($file->getFilename(), PATHINFO_EXTENSION);
      }
      else {
        $variables['extension'] = t('External');
      }
    }
  }

  // Hide the white area (main content block) from the EIF Toolbox.
  if ((int) $node->id() === EifInterface::EIF_ONLINE_ASSISTANT) {
    unset($variables['content']);
  }
}

/**
 * Implements hook_preprocess_HOOK() for node--moderation.html.twig.
 *
 * - Removes the contextual links.
 * - Adds an edit URL.
 */
function ventuno_preprocess_node__moderation(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  $variables['edit_url'] = $node->toUrl('edit-form')->toString();
}

/**
 * Implements hook_preprocess_page_title__node__distribution().
 */
function ventuno_preprocess_page_title__node__distribution(array &$variables): void {
  /** @var \Drupal\joinup_distribution\Entity\DistributionInterface $entity */
  $entity = $variables['entity'];

  // Get the view builder service from the entity type manager.
  $view_builder = \Drupal::entityTypeManager()->getViewBuilder('rdf_entity');
  // Get the file size and licence fields.
  $file_size = $entity->file_size;
  $licence = $entity->field_licence;
  $file_size = $view_builder->viewField($file_size, 'default');
  $licence = $view_builder->viewField($licence, 'default');

  $variables['additional_content']['file_size'] = $file_size;
  $variables['additional_content']['licence'] = $licence;
}
