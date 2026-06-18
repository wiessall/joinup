<?php

declare(strict_types=1);

namespace Drupal\topic\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Hook implementations for the Topic module.
 */
final class TopicHooks {

  public function __construct(
    private readonly AccountProxyInterface $currentUser,
  ) {}

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
    if ($this->currentUser->hasPermission('administer taxonomy')) {
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
