<?php

declare(strict_types=1);

namespace Drupal\interoperable_europe;

/**
 * Interface for the Interoperable Europe collection.
 */
interface InteroperableEuropeCollectionInterface {

  /**
   * The node UUID of the Interoperable Europe collection.
   */
  public const COLLECTION_UUID = '8e30f798-ff2b-478b-9c09-5ed5a63b4c8c';

  /**
   * The UUID of the news listing page.
   */
  public const NEWS_LISTING = '85aa19c4-58f6-4681-8105-3bf98ce7df4e';

  /**
   * The UUID of the events listing page.
   */
  public const EVENT_LISTING = '240cf939-639b-46bb-b907-f768b8f37267';

  /**
   * The UUID of the FAQ page.
   */
  public const FAQ = '791b9aa0-d417-411a-96af-a96bb546992a';

  /**
   * The UUID of the Policy page.
   */
  public const POLICY = '2c05b457-705f-4c1a-b667-b06c9b612e02';

}
