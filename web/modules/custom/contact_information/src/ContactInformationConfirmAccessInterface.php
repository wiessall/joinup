<?php

declare(strict_types = 1);

namespace Drupal\contact_information;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\contact_information\Entity\ContactInformation;

/**
 * Default interface for contact_information.email_confirm_access.
 */
interface ContactInformationConfirmAccessInterface {

  /**
   * Access check for the confirmation page.
   *
   * @param \Drupal\contact_information\Entity\ContactInformation $contact_information
   *   Contact Information entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result object.
   */
  public function access(ContactInformation $contact_information): AccessResultInterface;

}
