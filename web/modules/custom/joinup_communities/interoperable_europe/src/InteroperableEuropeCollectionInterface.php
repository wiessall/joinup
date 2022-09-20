<?php

declare(strict_types = 1);

namespace Drupal\interoperable_europe;

/**
 * Interface for the Interoperable Europe collection.
 */
interface InteroperableEuropeCollectionInterface {

  /**
   * The RDF entity ID of the Interoperable Europe collection.
   */
  public const COLLECTION_ENTITY_ID = 'http://data.europa.eu/w21/8e30f798-ff2b-478b-9c09-5ed5a63b4c8c';

  /**
   * The NID of the news listing page.
   */
  public const NEWS_LISTING = '704825';

  /**
   * The NID of the events listing page.
   */
  public const EVENT_LISTING = '704828';

  /**
   * The NID of the FAQ page.
   */
  public const FAQ = '705665';

}
