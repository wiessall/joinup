<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use Drupal\joinup\Traits\SearchTrait;
use Drupal\joinup\Traits\TopicTrait;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\node\Entity\Node;

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
    Node::create([
      'type' => 'collection',
      'uri' => JoinupCollectionHelper::JOINUP_COLLECTION_URI,
      'title' => 'Joinup',
      'field_state' => 'published',
      'field_topic' => $this->getRandomTopic()->id(),
      'owner' => $owner->nid,
      'contact_information' => $contact->nid,
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
    $collection = \Drupal::getContainer()->get('rdf_sync.mapper')->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    $collection->skip_notification = TRUE;
    $collection->delete();
  }

}
