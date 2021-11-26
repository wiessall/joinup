<?php

declare(strict_types = 1);

namespace Drupal\joinup\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\node\Entity\Node;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\taxonomy\Entity\Term;

/**
 * Behat step definitions for interoperating with Europe.
 */
class InteroperableEuropeContext extends RawDrupalContext {

  use SearchTrait;

  /**
   * Creates the Interoperable Europe collection and its landing page.
   *
   * @BeforeScenario @interoperable-europe&&@api
   */
  public function createInteroperableEuropeContent(BeforeScenarioScope $scope): void {
    // Create a topic to associate with the collection.
    Term::create([
      'vid' => 'topic',
      'tid' => 'http://example.com/term/interoperability',
      'name' => 'Interoperability',
    ])->save();

    // Create the collection owner.
    Rdf::create([
      'rid' => 'owner',
      'id' => 'http://example.com/owner/interoperable-europe',
      'field_owner_name' => 'Interoperable Europe',
    ])->save();

    // Create the collection contact.
    Rdf::create([
      'rid' => 'contact_information',
      'id' => 'http://example.com/contact/interoperable-europe',
      'field_contact_name' => 'Interoperable Europe',
      'field_contact_email' => 'interoperable.europe@ec.europa.eu',
    ])->save();

    // Create the Interoperable Europe collection.
    Rdf::create([
      'rid' => 'collection',
      'id' => InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID,
      'label' => 'Interoperable Europe',
      'field_ar_state' => 'validated',
      'field_topic' => 'http://example.com/term/interoperability',
      'field_ar_owner' => 'http://example.com/owner/interoperable-europe',
      'field_ar_contact_information' => 'http://example.com/contact/interoperable-europe',
    ])->save();

    // Create the landing page.
    Node::create([
      'type' => 'custom_page',
      'nid' => InteroperableEuropeCollectionInterface::LANDING_PAGE_ENTITY_ID,
      'title' => 'Interoperable Europe',
      'og_audience' => InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID,
    ])->save();
  }

  /**
   * Removes the Interoperable Europe content after the scenario ends.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when one of the created entities could not be deleted.
   *
   * @AfterScenario @interoperable-europe&&@api
   */
  public function cleanInteroperableEuropeContent(AfterScenarioScope $scope): void {
    // Temporarily disable the feature to commit the index after every query.
    $this->disableCommitOnUpdate();

    Node::load(InteroperableEuropeCollectionInterface::LANDING_PAGE_ENTITY_ID)->delete();

    $rdf_ids = [
      InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID,
      'http://example.com/contact/interoperable-europe',
      'http://example.com/owner/interoperable-europe',
    ];
    foreach ($rdf_ids as $id) {
      $entity = Rdf::load($id);
      $entity->skip_notification = TRUE;
      $entity->delete();
    }

    Term::load('http://example.com/term/interoperability')->delete();

    $this->enableCommitOnUpdate();
  }

}
