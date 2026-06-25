<?php

declare(strict_types=1);

namespace Drupal\topic\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\taxonomy\TermInterface;
use Drupal\topic\Entity\TopicTerm;
use Drupal\topic\Entity\TopicTermInterface;

/**
 * Hook implementations related to topic taxonomy terms.
 */
final class TopicTermHooks {

  use StringTranslationTrait;

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly RouteMatchInterface $routeMatch,
  ) {}

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    if (!empty($bundles['taxonomy_term']['topic'])) {
      $bundles['taxonomy_term']['topic']['class'] = TopicTerm::class;
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_create_access() for paragraphs.
   *
   * Allows creation of the configurable_listing paragraph only on topic
   * vocabulary term edit forms, and denies other paragraph types there.
   */
  #[Hook('paragraph_create_access')]
  public function paragraphCreateAccess(AccountInterface $account, array $context, string $entity_bundle): AccessResultInterface {
    if ($this->routeMatch->getRouteName() !== 'entity.taxonomy_term.edit_form') {
      return AccessResult::neutral()->addCacheContexts(['route']);
    }

    $term = $this->routeMatch->getParameter('taxonomy_term');
    if (!$term instanceof TopicTermInterface) {
      return AccessResult::neutral()->addCacheContexts(['route']);
    }

    if ($entity_bundle === 'configurable_listing') {
      return AccessResult::allowed()
        ->addCacheableDependency($term)
        ->addCacheContexts(['route']);
    }

    $denied_paragraphs = [
      'accordion',
      'call_to_action',
      'carousel',
      'content_listing',
      'iframe',
      'image',
      'json_map',
      'testimony',
    ];

    if (in_array($entity_bundle, $denied_paragraphs, TRUE)) {
      return AccessResult::forbidden()
        ->addCacheableDependency($term)
        ->addCacheContexts(['route']);
    }

    return AccessResult::neutral()->addCacheContexts(['route']);
  }

  /**
   * Implements hook_entity_access().
   */
  #[Hook('entity_access')]
  public function entityAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if (!$entity instanceof TermInterface || $entity->bundle() !== 'topic' || $operation !== 'update') {
      return AccessResult::neutral();
    }
    return AccessResult::allowedIfHasPermission($account, 'edit topic term description')
      ->addCacheableDependency($entity);
  }

  /**
   * Implements hook_form_taxonomy_term_topic_edit_form_alter().
   */
  #[Hook('form_taxonomy_term_topic_form_alter')]
  public function formTaxonomyTermTopicEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $term = $form_state->getFormObject()->getEntity();
    if (!$term instanceof TopicTermInterface || $this->currentUser->hasPermission('administer taxonomy')) {
      return;
    }

    $restricted = ['name', 'relations', 'revision_information'];
    foreach ($restricted as $field) {
      if (isset($form[$field])) {
        $form[$field]['#access'] = FALSE;
      }
    }

    if (isset($form['actions']['overview'])) {
      $form['actions']['overview']['#access'] = FALSE;
    }
  }

}
