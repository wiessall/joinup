<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope;
use Drupal\email_confirmer\EmailConfirmationInterface;
use Drupal\joinup\Traits\AliasTranslatorTrait;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\MailCollectorTrait;
use Drupal\joinup\Traits\NodeTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Assert;

/**
 * Behat step definitions for testing contact information entities.
 */
class ContactInformationContext extends RawDrupalContext {

  use AliasTranslatorTrait;
  use EntityTrait;
  use MailCollectorTrait;
  use NodeTrait;
  use RdfEntityTrait;
  use SearchTrait;
  use TestingEntitiesTrait;
  use UserTrait;

  /**
   * Navigates to the canonical page display of a contact information entity.
   *
   * @param string $label
   *   The label of the contact information entity.
   *
   * @When I go to (the homepage of )the :label contact
   * @When I visit (the homepage of )the :label contact
   * @When I go to the :label contact information page
   * @When I visit the :label contact information page
   *
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function visitContactInformationPage(string $label): void {
    /** @var \Drupal\rdf_entity\Entity\Rdf $entity */
    $entity = $this->getEntityByLabel('node', $label, 'contact_information');
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Acts before node creation and translates the human readable fields.
   *
   * @param \Drupal\DrupalExtension\Hook\Scope\BeforeNodeCreateScope $scope
   *   The before node create scope.
   *
   * @BeforeNodeCreate
   */
  public function beforeNodeCreate(BeforeNodeCreateScope $scope): void {
    $node = $scope->getEntity();
    if ($node->type !== 'contact_information') {
      return;
    }

    $keys = array_keys((array) $node);
    foreach ($keys as $key) {
      if ($key === 'type') {
        continue;
      }

      // Replace the column aliases with the actual field names.
      $translated = self::translateFieldNameAlias($key, self::contactInformationFieldAliases());
      $node->$translated = $node->$key;
      unset($node->$key);
    }
  }

  /**
   * Checks the number of available contact information entities.
   *
   * @param int $number
   *   The expected number of entities.
   *
   * @throws \Exception
   *   Throws an exception when the expected number is not equal to the given.
   *
   * @Then I should have :number contact information(s)
   */
  public function assertContactInformationCount(int $number): void {
    $this->assertNodeCount($number, 'contact_information');
  }

  /**
   * Deletes an contact information entity.
   *
   * @param string $name
   *   The name of the contact information to delete.
   *
   * @When I delete the :contact contact information
   */
  public function deleteContactInformation(string $name): void {
    $this->getEntityByLabel('node', $name, 'contact_information')->delete();
  }

  /**
   * Creates a contact information from the given property and field data.
   *
   * @param array $values
   *   An optional associative array of values, keyed by property name.
   *
   * @return \Drupal\node\NodeInterface
   *   A new contact information entity.
   *
   * @throws \Exception
   *   When the author is specified but the related user doesn't exist.
   */
  protected function createContactInformation(array $values): NodeInterface {
    // The 'author' key was replaced by 'uid' in the calling function.
    if (!empty($values['uid'])) {
      $values['uid'] = $this->getUserByName($values['uid'])->id();
    }

    $values += [
      'type' => 'contact_information',
    ];
    $node = $this->nodeCreate((object) $values);
    return Node::load($node->nid);
  }

  /**
   * Field alias mapping.
   *
   * @return array
   *   Mapping.
   */
  protected static function contactInformationFieldAliases(): array {
    // Mapping alias - field name.
    return [
      'email' => 'ci_email',
      'name' => 'title',
      'website url' => 'ci_webpage',
      'author' => 'uid',
      'state' => 'field_state',
    ];
  }

  /**
   * Clicks the validate contact info link found in an email.
   *
   * @param string $mail
   *   The recipient E-mail address.
   *
   * @throws \Exception
   *   Thrown when a user is not found.
   *
   * @When I click the validate contact info email from the last email sent to :mail
   */
  public function clickValidateContactInfoInEmail(string $mail): void {
    $pattern = '#\[(?<url>https?://[^/].*' . base_path() . 'email-confirmer/reply/[^/].*/[^]].*)\]#';
    $no_mail_message = "No email was sent to $mail containing a validate contact info link.";
    $no_match_message = "The email sent to $mail did not contain a valid validate contact info link.";
    $this->assertMailLinkMatchingPattern($pattern, $mail, $no_mail_message, $no_match_message, 'confirmation_request');
  }

  /**
   * Validates the emails of given list of contact information entities.
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_info_list
   *   A list of contact information labels.
   *
   * @Given the following contact information emails are validated:
   */
  public function validateContactInformationEmails(TableNode $contact_info_list): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    foreach ($contact_info_list->getColumn(0) as $label) {
      /** @var \Drupal\contact_information\Entity\ContactInformationInterface $contact_info */
      $contact_info = $this->getEntityByLabel('node', $label, 'contact_information');
      $email_field = $contact_info->get('ci_email');
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
   *
   * @Given that the pending confirmation request for :email has expired
   */
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
   *
   * @Then :count :status confirmation request(s) for :email_confirmation should exist
   */
  public function assertNumberOfConfirmationRequests(string $count, string $status, string $email_confirmation): void {
    Assert::assertTrue(in_array($count, ['no', 'a', 'an']), "The count '$count' is not supported.");

    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email_confirmation, $status, ContactInformationEmailConfirmInterface::REALM);
    if ($count === 'no') {
      Assert::assertEmpty($confirmation, "There should be no confirmation requests with status '$status' for '$email_confirmation'.");
    }
    else {
      Assert::assertNotEmpty($confirmation, "There should be at least one confirmation request with status '$status' for '$email_confirmation'.");
    }
  }

  /**
   * Asserts that a confirmation request for an email is expired.
   *
   * @param string $email
   *   The email to check.
   *
   * @Given the confirmation request for :email should be expired
   */
  public function givenConfirmationExpired(string $email): void {
    $email_confirmer = \Drupal::getContainer()->get('email_confirmer');
    $confirmation = $email_confirmer->getConfirmation($email, FALSE, ContactInformationEmailConfirmInterface::REALM);
    if (empty($confirmation)) {
      throw new \RuntimeException("No confirmation request found for $email");
    }
    Assert::assertTrue($confirmation->isExpired(), 'Confirmation request is expired.');
  }

  /**
   * Asserts that a confirmation request for an email is not expired.
   *
   * @param string $email
   *   The email to check.
   *
   * @Given the confirmation request for :email should not be expired
   */
  public function givenConfirmationNotExpired(string $email): void {
    $confirmation = $this->getEmailConfirmation($email, NULL);
    Assert::assertFalse($confirmation->isExpired(), 'Confirmation request is not expired.');
  }

  /**
   * Confirms a confirmation request for an email.
   *
   * @param string $email
   *   The email to confirm.
   *
   * @Given that the pending confirmation request for :email has been confirmed
   */
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
   *
   * @Given that the pending confirmation request for :email has been cancelled
   */
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
   * Cleans up confirmation requests for example.com emails.
   *
   * @param \Behat\Behat\Hook\Scope\AfterScenarioScope $scope
   *   The after scenario scope.
   *
   * @AfterScenario
   */
  public function afterScenarioConfirmationCleanup(AfterScenarioScope $scope): void {
    $ids = \Drupal::entityQuery('email_confirmer_confirmation')
      ->accessCheck(FALSE)
      ->condition('email', '%example.com', 'LIKE')
      ->execute();

    if (empty($ids)) {
      return;
    }
    $storage = \Drupal::entityTypeManager()->getStorage('email_confirmer_confirmation');
    $storage->delete($storage->loadMultiple($ids));
  }

  /**
   * Creates confirmation requests for the given emails.
   *
   * @Given the following email confirmation request(s):
   */
  public function theFollowingEmailConfirmationRequest(TableNode $table) {
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

  /**
   * Asserts no multiple confirmed requests of the same email exist.
   *
   * @todo Remove this in the next release cleanup.
   *
   * @Given no multiple confirmed confirmations exist for the same email
   */
  public function noMultipleConfirmedConfirmationsExistForTheSameEmail(): void {
    $query = <<<MYSQL
SELECT ec.email AS email
FROM {email_confirmer_confirmation} ec WHERE
    (ec.confirmed = 1)
    AND (ec.status = 1)
    AND (ec.realm = 'contact_information_email')
GROUP BY email
HAVING (COUNT(email) > 1)
MYSQL;

    $result = \Drupal::database()->query($query)->fetchAll();
    if (!empty($result)) {
      throw new \RuntimeException('Multiple confirmed confirmations exist for the same email.');
    }
  }

}
