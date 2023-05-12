<?php

/**
 * @file
 * Node specific procedural functions.
 */

declare(strict_types = 1);

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eif\EifInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_workflow\ArchivableEntityInterface;

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
 * Implements hook_preprocess_node().
 */
function ventuno_preprocess_node__view_mode_tile(array &$variables, string $entity_type_id): void {
  $entity = $variables[$entity_type_id];
  /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
  $date_formatter = Drupal::service('date.formatter');

  // Info (above the title).
  $variables['info']['icon'] = 'calendar-event';
  if ($changed_time = $entity->getChangedTime()) {
    $variables['info']['label'] = t('@label: @changed', [
      '@label' => t('Last update'),
      '@changed' => \Drupal::service('date.formatter')->format($changed_time, 'date_only'),
    ]);
  }

  // For now, only Custom page, Discussion, Document, Event and News items
  // (all of them are nodes) can be archived.
  if ($entity instanceof ArchivableEntityInterface
    && $entity->get('field_state')->value === 'archived'
  ) {
    $variables['extra_data'][] = [
      'icon' => 'trash',
      'label' => t('Archived'),
    ];
  };

  switch ($entity->bundle()) {
    case 'discussion':
      $variables['info']['icon'] = 'calendar-event';
      if ($changed_time = $entity->getChangedTime()) {
        $variables['info']['label'] = t('@label: @changed', [
          '@label' => t('Last activity'),
          '@changed' => $date_formatter->format($changed_time, 'custom', 'd/m/Y, h:s'),
        ]);
      }
      $node = $variables['elements']['#node'];
      $variables['extra_data'][] = [
        'icon' => 'chat',
        'label' => $node->get('field_replies')->comment_count,
      ];
      break;

    case 'event':
      $variables['highlighted']['icon'] = 'calendar-event';
      $variables['highlighted']['text'] = $variables['content']['field_event_date'];
      $variables['info']['icon'] = 'geo-alt';
      $node = $variables['elements']['#node'];
      if (!$node->get('field_location')->isEmpty() && !$node->get('field_event_online_location')->isEmpty()) {
        $variables['info']['label'] = t('Hybrid event');
      }
      elseif ($node->get('field_location')->isEmpty()) {
        $variables['info']['label'] = t('Virtual event');
      }
      else {
        $variables['info']['label'] = t('Physical event');
      }
      break;

    case 'tallinn_report':
      $variables['info']['icon'] = 'calendar-event';
      if ($entity->getCreatedTime() === $entity->getChangedTime()) {
        $variables['info']['label'] = new TranslatableMarkup('@label: -', [
          '@label' => 'Last update',
        ]);
      }
      elseif ($changed_time = $entity->getChangedTime()) {
        $variables['info']['label'] = t('@label: @changed', [
          '@label' => t('Last update'),
          '@changed' => $date_formatter->format($changed_time, 'date_only'),
        ]);
      }
      break;
  }
  _ventuno_preprocess_tile($variables, 'node');
}
