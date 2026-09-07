<?php

declare(strict_types=1);

namespace Drupal\contact_form\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;

/**
 * Hook implementations for contact form access.
 */
final class ContactFormAccessHooks {

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('message_access')]
  public function messageAccess(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    if ($entity->bundle() !== 'contact_form_submission') {
      return AccessResult::neutral();
    }

    // Allow users to access the contact form messages and its attachments.
    return AccessResult::allowedIfHasPermission($account, 'access contact form messages');
  }

}
