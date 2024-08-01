<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\collection\JoinupCollectionHelper;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TopicTrait;
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
    $owner = $this->nodeCreate((object) [
      'type' => 'owner',
      'title' => 'ISA²',
    ]);

    // Create the contact.
    $contact = $this->nodeCreate((object) [
      'type' => 'contact_information',
      'title' => 'Contact name',
      'email' => 'contact.email@example.com',
    ]);

    // Create the Joinup collection.
    Rdf::create([
      'rid' => 'collection',
      'id' => JoinupCollectionHelper::JOINUP_COLLECTION_DEFAULT_ENTITY_ID,
      'label' => 'Joinup',
      'field_ar_state' => 'published',
      'field_topic' => $this->getRandomTopic()->id(),
      'field_ar_owner' => $owner->nid,
      'field_ar_contact_information' => $contact->nid,
    ])->save();
  }

  /**
   * Clears the Joinup collection created before the scenario.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown when one of the created entities could not be deleted.
   *
   * @AfterScenario @joinup&&@api
   */
  public function cleanJoinupData(): void {
    $collection = Rdf::load(JoinupCollectionHelper::JOINUP_COLLECTION_DEFAULT_ENTITY_ID);
    $collection->skip_notification = TRUE;
    $collection->delete();
  }

}
