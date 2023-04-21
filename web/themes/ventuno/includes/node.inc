<?php

/**
 * @file
 * Node specific procedural functions.
 */

declare(strict_types = 1);

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
 * Implements hook_preprocess_HOOK() for node--tallinn-report--view-mode-tile.html.twig.
 */
function ventuno_preprocess_node__tallinn_report__view_mode_tile(&$variables) {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  $variables['last_update'] = '-';
  if (!$node->get('created')->equals($node->get('changed'))) {
    $date = \Drupal::service('date.formatter')->format($node->getChangedTime(), 'custom', 'd/m/Y');
    $variables['last_update'] = $date;
  }
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
  _ventuno_preprocess_search_result($variables, 'node');
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];

  // Add the site URL to use in nodes.
  $variables['site_url'] = \Drupal::request()->getSchemeAndHttpHost();

  // Add the group ID and label as data attributes in tiles.
  if ($variables['view_mode'] === 'view_mode_tile') {
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

  // Add bundle class in listing templates.
  if ($variables['view_mode'] === 'view_mode_tile' || $variables['view_mode'] === 'view_mode_featured') {
    $bundle_class = 'listing__card--' . str_replace('_', '-', $node->bundle());
    $variables['attributes']['class'][] = $bundle_class;
  }

  // Add the created date to news articles.
  if ($node->bundle() === 'news') {
    $variables['created'] = \Drupal::service('date.formatter')->format($node->getCreatedTime(), 'date_only');
  }

  // Add a variable indicating the attachment file type on documents.
  if ($node->bundle() === 'document' && !$node->get('field_file')->isEmpty()) {
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
 * Implements hook_preprocess_HOOK() for node--news--view-mode-tile.html.twig.
 */
function ventuno_preprocess_node__news__view_mode_tile(array &$variables): void {
  $node = $variables['elements']['#node'];
  $variables['changed'] = Drupal::service('date.formatter')->format($node->getChangedTime(), 'date_only');
}

/**
 * Implements hook_preprocess_HOOK() for node--document--view-mode-tile.html.twig.
 */
function ventuno_preprocess_node__document__view_mode_tile(array &$variables): void {
  $node = $variables['elements']['#node'];
  $variables['changed'] = Drupal::service('date.formatter')->format($node->getChangedTime(), 'date_only');
}

/**
 * Implements hook_preprocess_HOOK() for node--discussion--view-mode-tile.html.twig.
 */
function ventuno_preprocess_node__discussion__view_mode_tile(array &$variables): void {
  $node = $variables['elements']['#node'];
  $variables['comment_count'] = $node->get('field_replies')->comment_count;
  $variables['changed'] = Drupal::service('date.formatter')->format($node->getChangedTime(), 'custom', 'd/m/Y, h:s');
}

/**
 * Implements hook_preprocess_HOOK() for node--custom-page--view-mode-tile.html.twig.
 */
function ventuno_preprocess_node__custom_page__view_mode_tile(array &$variables): void {
  $node = $variables['elements']['#node'];
  $variables['changed'] = Drupal::service('date.formatter')->format($node->getChangedTime(), 'date_only');
}
