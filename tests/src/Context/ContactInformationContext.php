<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\AliasTranslatorTrait;
use Drupal\joinup\Traits\MailCollectorTrait;
use Drupal\joinup\Traits\RdfEntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TestingEntitiesTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\rdf_entity\RdfInterface;

/**
 * Behat step definitions for testing contact information entities.
 */
class ContactInformationContext extends RawDrupalContext {

  use AliasTranslatorTrait;
  use MailCollectorTrait;
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
    $entity = $this->getRdfEntityByLabel($label, 'contact_information');
    $this->visitPath($entity->toUrl()->toString());
  }

  /**
   * Creates a contact information entity with data provided in a table.
   *
   * Table format:
   * | email | foo@bar.com, baz@qux.com |
   * | name  | Jack Smith               |
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_table
   *   The contact table.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )contact:
   */
  public function givenContactEntity(TableNode $contact_table): void {
    $values = [];

    foreach ($contact_table->getRowsHash() as $key => $value) {
      // Replace the column aliases with the actual field names.
      $key = self::translateFieldNameAlias($key, self::contactInformationFieldAliases());
      $values[$key] = $value;
    }

    $this->createContactInformation($values);
  }

  /**
   * Creates a number of contact information with data provided in a table.
   *
   * Table format:
   * @codingStandardsIgnoreStart
   * | name                   | email                            | website url                            | author      |
   * | John Irwin             | foo@example.com, bar@example.com | http://google.com                      | Author name |
   * | Jack Smith, John Irwin | baz@example.com                  | http://yahoo.com, http://altavista.com |             |
   * @codingStandardsIgnoreEnd
   *
   * @param \Behat\Gherkin\Node\TableNode $contact_table
   *   The contacts table.
   *
   * @throws \Exception
   *   Thrown when a column name is incorrect.
   *
   * @Given (the following )contacts:
   */
  public function givenContactEntities(TableNode $contact_table): void {
    foreach ($contact_table->getColumnsHash() as $entity) {
      $values = [];

      foreach ($entity as $key => $value) {
        $key = self::translateFieldNameAlias($key, self::contactInformationFieldAliases());
        $values[$key] = $value;
      }

      $this->createContactInformation($values);
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
    $this->assertRdfEntityCount($number, 'contact_information');
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
    $this->getRdfEntityByLabel($name, 'contact_information')->delete();
  }

  /**
   * Creates a contact information from the given property and field data.
   *
   * @param array $values
   *   An optional associative array of values, keyed by property name.
   *
   * @return \Drupal\rdf_entity\RdfInterface
   *   A new contact information entity.
   *
   * @throws \Exception
   *   When the author is specified but the related user doesn't exist.
   */
  protected function createContactInformation(array $values): RdfInterface {
    // The 'author' key was replaced by 'uid' in the calling function.
    if (!empty($values['uid'])) {
      $values['uid'] = $this->getUserByName($values['uid'])->id();
    }

    $entity = $this->createRdfEntity('contact_information', $this->parseRdfEntityFields($values));
    $this->entities['rdf_entity'][$entity->id()] = $entity;

    return $entity;
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
      'uri' => 'id',
      'email' => 'field_ci_email',
      'name' => 'field_ci_name',
      'website url' => 'field_ci_webpage',
      'author' => 'uid',
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

}
