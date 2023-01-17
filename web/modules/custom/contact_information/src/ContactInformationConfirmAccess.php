<?php

declare(strict_types = 1);

namespace Drupal\contact_information;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\contact_information\Entity\ContactInformation;
use Drupal\og\OgRoleInterface;

/**
 * Default implementation for 'contact_information.email_confirm_access service.
 */
class ContactInformationConfirmAccess implements ContactInformationConfirmAccessInterface {

  /**
   * Constructs a new contact information confirm access service.
   *
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user service.
   */
  public function __construct(protected AccountInterface $current_user) {}

  /**
   * {@inheritdoc}
   */
  public function access(ContactInformation $contact_information): AccessResultInterface {
    $user = $this->current_user;
    foreach ($user->getRoles() as $role) {
      if (in_array($role, ['administrator', 'moderator'])) {
        return AccessResult::allowed();
      }
    }
    $membership = $contact_information->getRelatedGroup()->getMembership();
    if (!empty($membership)) {
      $og_roles = $membership->getRoles();

      $role_names = array_map(function (OgRoleInterface $og_role) {
        return $og_role->getName();
      }, $og_roles);

      $is_authorized = (bool) count(array_intersect($role_names,
        ['administrator', 'facilitator']));

      if ($is_authorized) {
        return AccessResult::allowed();
      }
      return AccessResult::forbidden();
    }
    return AccessResult::forbidden();
  }

}
