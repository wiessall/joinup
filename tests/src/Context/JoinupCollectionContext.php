<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\rdf_entity\Entity\Rdf;

/**
 * Behat step definitions and related methods related to the Joinup collection.
 */
class JoinupCollectionContext extends RawDrupalContext {

  use EntityTrait;
  use SearchTrait;
  use TopicTrait;

  /**
   * Creates the 'Joinup' collection and related data.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when one of the entities could not be created, for example because
   *   it already exists.
   *
   * @BeforeScenario @joinup&&@api
   */
  public function setupJoinupCollection(): void {
    // Create the owner.
    Rdf::create([
      'rid' => 'owner',
      'id' => 'http://example.com/owner/isa2',
      'field_owner_name' => 'ISA²',
    ])->save();

    // Create the contact.
    Rdf::create([
      'rid' => 'contact_information',
      'id' => 'http://example.com/contact/joinup',
      'field_contact_name' => 'Contact name',
      'field_contact_email' => 'contact.email@example.com',
    ])->save();

    // Create the Joinup collection.
    Rdf::create([
      'rid' => 'collection',
      'id' => JoinupCollectionHelper::JOINUP_COLLECTION_DEFAULT_ENTITY_ID,
      'label' => 'Joinup',
      'field_ar_state' => 'published',
      'field_topic' => $this->getRandomTopic()->id(),
      'field_ar_owner' => 'http://example.com/owner/isa2',
      'field_ar_contact_information' => 'http://example.com/contact/joinup',
    ])->save();
  }

  /**
   * Clears the content created for the purpose of this test.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when one of the created entities could not be deleted.
   *
   * @AfterScenario @joinup&&@api
   */
  public function cleanJoinupData() {
    // Delete the Joinup collection.
    $collection = Rdf::load(JoinupCollectionHelper::JOINUP_COLLECTION_DEFAULT_ENTITY_ID);
    $collection->skip_notification = TRUE;
    $collection->delete();

    // Delete related entities.
    Rdf::load('http://example.com/owner/isa2')->delete();
    Rdf::load('http://example.com/contact/joinup')->delete();
  }

}
