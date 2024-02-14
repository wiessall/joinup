<?php

declare(strict_types=1);

namespace Drupal\contact_information;

use Drupal\contact_information\Entity\ContactInformationInterface;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Provides an interface for 'contact_information.email_confirm' service.
 */
interface ContactInformationEmailConfirmInterface {

  /**
   * Used to distinguish this kind of email confirmation.
   *
   * @var string
   */
  const REALM = 'contact_information_email';

  /**
   * Creates confirmation of emails belonging to contact information of a group.
   *
   * The method only creates email confirmation entries for emails not already
   * confirmed previously.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group containing the contact info for which to generate email
   *   confirmations.
   *
   * @return string[]
   *   A list of emails that were actually confirmed.
   */
  public function confirmEmailsForGroup(GroupInterface $group): array;

  /**
   * Confirm an email regardless of which contact information they belong.
   *
   * The method collects all contact information having a given email and
   * creates an email confirmation.
   *
   * @param string $email
   *   A contact information email.
   *
   * @return bool
   *   TRUE if a confirmation has been created. FALSE if a confirmation already
   *   exists or this email is not attached to any contact information entity or
   *   the contact information is not attached to any group.
   */
  public function confirmEmail(string $email): bool;

  /**
   * Returns a list of group IDs where the given email is used in contact infos.
   *
   * @param string $email
   *   The email used in contact information entities attached to groups that
   *   should be returned.
   *
   * @return string[]
   *   A list of groups IDs having attached contact information entities with
   *   the passed email.
   */
  public function getGroupsUsingContactInfoWithEmail(string $email): array;

  /**
   * Deletes unused confirmations for a given contact information entity.
   *
   * @param \Drupal\contact_information\Entity\ContactInformationInterface $contact_information
   *   The contact information for which to delete unused confirmations.
   */
  public function deleteContactInfoConfirmation(ContactInformationInterface $contact_information): void;

}
