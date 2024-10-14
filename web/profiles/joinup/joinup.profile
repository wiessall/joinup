<?php

/**
 * @file
 * Enables modules and site configuration for the Joinup profile.
 */

declare(strict_types=1);

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_featured\FeaturedContentInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_entity_type_alter().
 */
function joinup_entity_type_alter(array $entityTypes): void {
  // Add the "Propose" form operation to nodes entities so that we can add
  // propose form displays to them. Skip this during installation.
  if (isset($entityTypes['node'])) {
    $entityTypes['node']->setFormClass('propose', $entityTypes['node']->getFormClass('edit'));
  }
}

/**
 * Implements hook_form_FORM_ID_alter().
 */
function joinup_form_field_config_edit_form_alter(&$form): void {
  // Increase the maximum length of the file extension field to allow
  // registration of large amounts of extensions.
  if (isset($form['settings']['file_extensions']['#maxlength'])) {
    $form['settings']['file_extensions']['#maxlength'] = 1024;
  }
}

/**
 * Implements hook_entity_access().
 */
function joinup_entity_access(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
  // Moderators have the 'administer organic groups' permission so they can
  // manage all group content across all groups. However since the OG Menu
  // entities are also group content moderators are also granted access to the
  // OG Menu administration pages. Let's specifically deny access to these,
  // since we are handling the menu items transparently whenever custom pages
  // are created or deleted. Moderators and collection facilitators should only
  // have access to the edit form of an OG Menu instance so they can rearrange
  // the custom pages, but not to the entity forms of the menu items themselves.
  // In fact, nobody should have access to these pages except UID 1.
  if ($entity->getEntityTypeId() === 'ogmenu_instance' && $operation !== 'update') {
    return AccessResult::forbidden();
  }

  return AccessResult::neutral();
}

/**
 * Implements hook_field_widget_inline_entity_form_complex_form_alter().
 *
 * Simplifies the widget buttons when only a bundle is configured.
 */
function joinup_field_widget_inline_entity_form_complex_form_alter(&$element, FormStateInterface $form_state, $context): void {
  if (isset($element['actions']['bundle']['#type']) && $element['actions']['bundle']['#type'] == 'value') {
    $buttons = [
      'ief_add' => t('Add new'),
      'ief_add_existing' => t('Add existing'),
    ];

    foreach ($buttons as $key => $label) {
      if (!empty($element['actions'][$key])) {
        $element['actions'][$key]['#value'] = $label;
      }
    }
  }
}

/**
 * Implements hook_inline_entity_form_reference_form_alter().
 */
function joinup_inline_entity_form_reference_form_alter(&$reference_form, &$form_state): void {
  // Avoid showing two labels one after each other.
  $reference_form['entity_id']['#title_display'] = 'invisible';
}

/**
 * Implements hook_form_FORM_ID_alter().
 *
 * - Disable access to the revision information vertical tab.
 *   This prevents access to the revision log and the revision checkbox too.
 * - Disable access to the comment settings. These are managed on collection
 *   level.
 * - Disable access to the meta information.
 * - Allow access to the uid field only to the moderators.
 */
function joinup_form_node_form_alter(&$form, FormStateInterface $form_state, $form_id): void {
  $form['revision_information']['#access'] = FALSE;
  $form['revision']['#access'] = FALSE;
  $form['meta']['#access'] = FALSE;

  if (isset($form['uid'])) {
    $form['uid']['#access'] = \Drupal::currentUser()->hasPermission('administer nodes');
  }

  foreach (['field_comments', 'field_replies'] as $field) {
    if (!empty($form[$field])) {
      $form[$field]['#access'] = FALSE;
    }
  }
}

/**
 * Implements hook_theme().
 */
function joinup_theme($existing, $type, $theme, $path): array {
  $page_template = [
    'variables' => [],
    'path' => \Drupal::service('extension.list.profile')->getPath('joinup') . '/templates',
  ];
  return [
    'joinup_eligibility_criteria' => $page_template,
    'joinup_legal_notice' => $page_template,
    'joinup_modal_close_button' => [
      'variables' => [
        'label' => t('Got it'),
        'url' => '',
        'attributes' => [],
      ],
    ] + $page_template,
  ];
}

/**
 * Implements hook_entity_view_alter().
 *
 * Adds metadata needed to show relevant contextual links whenever entities are
 * displayed.
 */
function joinup_entity_view_alter(array &$build, EntityInterface $entity, EntityViewDisplayInterface $display): void {
  if ($entity->getEntityTypeId() === 'node' && $display->getMode() !== 'group_header') {
    // Add the "entity" contextual links group. Avoid overwriting any existing
    // data that might have been added by other modules. Because of a bug in
    // Drupal core we cannot alter the running order of hook_entity_view_alter()
    // when it is executed as an "extra hook". This means that we cannot use
    // hook_module_implements_alter() to define a proper running order for this
    // hook, so let's make sure that we do not lose any data set by other
    // modules which are supposed to run after us.
    // @see joinup_featured_entity_view_alter()
    // @see https://www.drupal.org/project/drupal/issues/3120298
    // @todo Revisit this in ISAICP-8376.
    $build['#contextual_links']['entity']['route_parameters']['entity_type'] = $entity->getEntityTypeId();
    $build['#contextual_links']['entity']['route_parameters']['entity'] = $entity->id();
    $build['#contextual_links']['entity']['metadata']['changed'] = $entity->getChangedTime();
  }

  if (!$entity instanceof PinnableGroupContentInterface) {
    return;
  }

  // The contextual links vary per user roles (since moderators are able to pin
  // content) and per OG roles (since facilitators are able to pin content).
  // Core already takes care of varying by roles by applying the
  // user.permissions cache context and applying the permission hash in the
  // contextual links. We need to include the corresponding data deriving from
  // the og_role cache context.
  /** @var \Drupal\og\Cache\Context\OgRoleCacheContext $cache_service */
  $cache_service = \Drupal::service('cache_context.og_role');
  $roles_hash = $cache_service->getContext();

  // The rendered entity needs to vary by OG group context.
  $build['#cache']['contexts'] = Cache::mergeContexts($build['#cache']['contexts'], [
    'og_role',
    'og_group_context',
  ]);

  /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
  $group = \Drupal::service('og.context')->getGroup();

  // The existence of the group context contextual links helps with enforcing
  // the og_context to the entity because otherwise there is nothing in the view
  // itself that would invalidate the tile and make it really `og_role`
  // dependant.
  $build['#contextual_links']['group_context'] = [
    'route_parameters' => [
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity->id(),
      // The group parameter is a required parameter in the pin/unpin
      // routes. If the parameter is left empty, a critical exception will
      // occur and the contextual links generation will break. By passing an
      // empty value, an upcast exception will be catched and the access
      // checks will correctly return an access denied.
      'group' => NULL,
    ],
    'metadata' => [
      'changed' => $entity->getChangedTime(),
      'og_roles_hash' => $roles_hash,
      'pin_status' => $entity->isPinned($group),
    ],
  ];

  // The next check asserts that the group is either a collection or a solution
  // but for solutions, only community content are allowed to be pinned, not
  // related solutions.
  if ($group && ($group instanceof CollectionInterface || $entity instanceof CommunityContentInterface && $group instanceof SolutionInterface)) {
    // Used by the contextual links for pinning/unpinning entity in group.
    // @see: joinup.pin_entity, joinup.unpin_entity routes.
    $build['#contextual_links']['group_context']['route_parameters']['group'] = $group->id();
  }
}

/**
 * Implements hook_preprocess_node().
 */
function joinup_preprocess_node(&$variables): void {
  _joinup_preprocess_entity_tiles($variables);
}

/**
 * Adds common functionality to the tile view mode of nodes.
 *
 * @param array $variables
 *   The variables array.
 */
function _joinup_preprocess_entity_tiles(array &$variables): void {
  // View modes where tiles are expected.
  $tile_view_modes = [
    'highlighted',
    'view_mode_tile',
    'view_mode_tile_horizontal',
    'carousel',
  ];
  if (!in_array($variables['view_mode'], $tile_view_modes)) {
    return;
  }

  /** @var \Drupal\Core\Entity\ContentEntityBase $entity */
  $entity = $variables[$variables['elements']['#entity_type']] ?? NULL;
  if (empty($entity)) {
    return;
  }

  // If the entity is featured site-wide, enable the related JS library.
  if ($entity instanceof FeaturedContentInterface && $entity->isFeatured()) {
    $variables['attributes']['data-joinup-featured'][] = TRUE;
    $variables['#attached']['library'][] = 'joinup/site_wide_featured';
  }
}

/**
 * Implements hook_views_pre_execute().
 *
 * Sets the view max age to tomorrow midnight when filtering down for upcoming
 * or past events.
 */
function joinup_views_pre_execute(ViewExecutable $view): void {
  $facets = _joinup_get_facets_by_facet_source_id('search_api:views_page__content_overview__page_1');

  if (
    !isset($facets['event_date']) ||
    empty(array_intersect(
      $facets['event_date']->getActiveItems(),
      ['upcoming_events', 'past_events']
    ))
  ) {
    return;
  }

  $max_age = (new DrupalDateTime('tomorrow'))->getTimestamp() - \Drupal::time()->getRequestTime();
  $view->display_handler->display['cache_metadata']['max-age'] = $max_age;
}

/**
 * Returns currently rendered facets filtered by facet source ID, keyed by ID.
 *
 * @param string $facet_source_id
 *   The facet source ID to filter by.
 *
 * @return \Drupal\facets\FacetInterface[]
 *   An array of facet, keyed by facet ID.
 */
function _joinup_get_facets_by_facet_source_id($facet_source_id): array {
  /** @var \Drupal\facets\FacetManager\DefaultFacetManager $facet_manager */
  $facet_manager = \Drupal::service('facets.manager');

  /** @var \Drupal\facets\FacetInterface[] $facets */
  $facets = [];
  foreach ($facet_manager->getFacetsByFacetSourceId($facet_source_id) as $facet) {
    $facets[$facet->id()] = $facet;
  }

  return $facets;
}
