<?php

/**
 * @file
 * Node specific procedural functions.
 */

declare(strict_types=1);

use Drupal\eif\EifInterface;
use Drupal\joinup_bundle_class\LogoInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\node\NodeInterface;

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

  // Provide variable to know whether the page has been set as the landing page
  // of a group, so we can add a <h1> title in the group header.
  $route_match = \Drupal::routeMatch();
  $variables['is_canonical'] = $route_match->getRouteName() === 'entity.node.canonical'
    && in_array($bundle, ['collection', 'solution']);

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

  // Add library specific to OSS Catalogue landing page.
  if ($variables['view_mode'] === 'full' && $node->uuid() === OssCatalogueCollectionInterface::LANDING_PAGE) {
    $variables['#attached']['library'][] = 'ventuno/oss-landing';
  }
}

/**
 * Implements hook_preprocess_HOOK() for node__solution__group-header.
 */
function ventuno_preprocess_node__solution__group_header(array &$variables): void {
  $node = $variables['node'];
  if (isset($variables['content']['releases_overview'])) {
    $variables['content']['releases_overview']['#attributes'] = [
      'class' => [
        'btn',
        'btn-secondary',
      ],
    ];
  }
  if ($node instanceof ArchivableEntityInterface) {
    $variables['is_archived'] = $node->isArchived();
  }
}

/**
 * Implements hook_preprocess_HOOK() for node--moderation.html.twig.
 *
 * - Removes the contextual links.
 * - Adds an edit URL.
 */
function ventuno_preprocess_node__moderation(&$variables): void {
  /** @var \Drupal\node\NodeInterface $node */
  $node = $variables['node'];
  $variables['edit_url'] = $node->toUrl('edit-form')->toString();
}

/**
 * Implements hook_preprocess_HOOK() for node__oss_solution__full.
 */
function ventuno_preprocess_node__oss_solution__full(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/oss-solution-full';
  $variables['content_attributes']['class'][] = 'layout--stack';
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

/**
 * Implements hook_theme_suggestions_HOOK_alter() for the joinup_release_releases_download hook.
 */
function ventuno_theme_suggestions_joinup_release_releases_download_alter(array &$suggestions, array $variables): void {
  $original_theme_hook = $variables['theme_hook_original'];
  $suggestions[] = $original_theme_hook;
}

/**
 * Implements hook_preprocess_HOOK() for node--digest-message.html.twig.
 */
function ventuno_preprocess_node__digest_message(&$variables): void {
  $node = $variables['node'];
  assert($node instanceof NodeInterface);

  $publicationTime = $node instanceof EntityPublicationTimeInterface
    ? $node->getPublicationTime()
    : NULL;
  $variables['publication_timestamp'] = $publicationTime ?: $node->getCreatedTime();
}
