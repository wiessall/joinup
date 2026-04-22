<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Step\Given;
use Behat\Step\Then;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\email_confirmer\EmailConfirmationInterface;
use Drupal\joinup\Traits\AliasTranslatorTrait;
use Drupal\joinup\Traits\AssertTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\MailCollectorTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\UserTrait;

/**
 * Behat step definitions for testing contact information entities.
 */
class ContactInformationContext extends RawDrupalContext {

  use AliasTranslatorTrait;
  use AssertTrait;
  use EntityTrait;
  use MailCollectorTrait;
  use NodeTrait;
  use SearchTrait;
  use UserTrait;

  /**
   * Validates the emails of given list of contact information entities.
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_info_list
   *   A list of contact information labels.
   */
  #[Given('the following contact information emails are validated:')]
  public function validateContactInformationEmails(TableNode $contact_info_list): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    foreach ($contact_info_list->getColumn(0) as $label) {
      /** @var \Drupal\contact_information\Entity\ContactInformationInterface $contact_info */
      $contact_info = $this->getEntityByLabel('node', $label, 'contact_information');
      $email_field = $contact_info->get('email');
      if (!$email_field->isEmpty()) {
        $confirmation = $email_confirmer->getConfirmation($email_field->value, 'pending') ?: $email_confirmer->createConfirmation($email_field->value);
        if (!$confirmation->confirm($confirmation->getHash())) {
          throw new \RuntimeException("Failed to confirm {$email_field->value}");
        }
        $confirmation->save();
      }
    }
  }

  /**
   * Expires a confirmation request for an email.
   *
   * @param string $email
   *   The email to expire.
   */
  #[Given('that the pending confirmation request for :email has expired')]
  public function givenConfirmationRequestExpired(string $email): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email, 'pending', ContactInformationEmailConfirmInterface::REALM);
    if (empty($confirmation)) {
      throw new \RuntimeException("No pending confirmation request found for $email");
    }

    $expiration_interval = \Drupal::configFactory()->get('email_confirmer.settings')->get('hash_expiration');
    // Subtract an additional hour to avoid unnecessary edge cases.
    $expired_date = $confirmation->getCreatedTime() - $expiration_interval - 3600;
    $confirmation->setCreatedTime($expired_date);
    $confirmation->set('sent', $expired_date);
    $confirmation->save();
  }

  /**
   * Asserts the number of confirmation requests with a status for an email.
   *
   * @param string $count
   *   The expected count.
   * @param string $status
   *   The requested status.
   * @param string $email_confirmation
   *   The email to check.
   */
  #[Then(':count :status confirmation request(s) for :email_confirmation should exist')]
  public function assertNumberOfConfirmationRequests(string $count, string $status, string $email_confirmation): void {
    $this->assertCondition(in_array($count, ['no', 'a', 'an']), "The count '$count' is not supported.");

    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email_confirmation, $status, ContactInformationEmailConfirmInterface::REALM);
    if ($count === 'no') {
      $this->assertCondition(empty($confirmation), "There should be no confirmation requests with status '$status' for '$email_confirmation'.");
    }
    else {
      $this->assertCondition(!empty($confirmation), "There should be at least one confirmation request with status '$status' for '$email_confirmation'.");
    }
  }

  /**
   * Asserts that a confirmation request for an email is expired.
   *
   * @param string $email
   *   The email to check.
   */
  #[Given('the confirmation request for :email should be expired')]
  public function givenConfirmationExpired(string $email): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email, FALSE, ContactInformationEmailConfirmInterface::REALM);
    if (empty($confirmation)) {
      throw new \RuntimeException("No confirmation request found for $email");
    }
    $this->assertCondition($confirmation->isExpired(), 'Confirmation request is expired.');
  }

  /**
   * Asserts that a confirmation request for an email is not expired.
   *
   * @param string $email
   *   The email to check.
   */
  #[Given('the confirmation request for :email should not be expired')]
  public function givenConfirmationNotExpired(string $email): void {
    $confirmation = $this->getEmailConfirmation($email, NULL);
    $this->assertCondition(!$confirmation->isExpired(), 'Confirmation request is not expired.');
  }

  /**
   * Confirms a confirmation request for an email.
   *
   * @param string $email
   *   The email to confirm.
   */
  #[Given('that the pending confirmation request for :email has been confirmed')]
  public function confirmConfirmationRequest(string $email): void {
    $confirmation = $this->getEmailConfirmation($email, 'pending');
    $confirmation->confirm($confirmation->getHash());
    $confirmation->save();
  }

  /**
   * Cancels a confirmation request for an email.
   *
   * @param string $email
   *   The email to confirm.
   */
  #[Given('that the pending confirmation request for :email has been cancelled')]
  public function cancelConfirmationRequest(string $email): void {
    $confirmation = $this->getEmailConfirmation($email, 'pending');
    $confirmation->cancel();
    $confirmation->save();
  }

  /**
   * Returns a confirmation email request.
   *
   * @param string $email
   *   The mail that the confirmation request refers to.
   * @param string|null $status
   *   (optional) The status of the confirmation request. Defaults to 'pending'.
   *
   * @return \Drupal\email_confirmer\EmailConfirmationInterface
   *   The confirmation request.
   */
  protected function getEmailConfirmation(string $email, ?string $status): EmailConfirmationInterface {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email, $status ?? FALSE, ContactInformationEmailConfirmInterface::REALM);
    if (empty($confirmation)) {
      throw new \RuntimeException("No pending confirmation request found for $email");
    }
    return $confirmation;
  }

  /**
   * Creates confirmation requests for the given emails.
   */
  #[Given('the following email confirmation request(s):')]
  public function theFollowingEmailConfirmationRequest(TableNode $table): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');

    foreach ($table->getHash() as $row) {
      $confirmation = $email_confirmer->createConfirmation($row['email']);
      $confirmation->setRealm(ContactInformationEmailConfirmInterface::REALM);
      $confirmation->setCreatedTime(\Drupal::time()->getRequestTime() - 3600);

      switch ($row['status']) {
        case 'confirmed':
          $confirmation->confirm($confirmation->getHash());
          break;

        case 'cancelled':
          $confirmation->cancel();
          break;

        case 'expired':
          $expiration_interval = \Drupal::configFactory()->get('email_confirmer.settings')->get('hash_expiration');
          // Subtract an additional hour to avoid unnecessary edge cases.
          $expired_date = $confirmation->getCreatedTime() - $expiration_interval - 3600;
          $confirmation->setCreatedTime($expired_date);
          break;

        default:
          break;
      }
      $confirmation->save();
    }
  }

}
