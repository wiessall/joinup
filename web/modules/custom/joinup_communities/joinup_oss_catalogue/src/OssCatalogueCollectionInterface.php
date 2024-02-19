<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

/**
 * Interface for the OSS catalogue collection.
 */
interface OssCatalogueCollectionInterface {

  /**
   * The RDF entity ID of the OSS catalogue.
   */
  public const COLLECTION_ENTITY_ID = 'http://data.europa.eu/w21/6d621a47-71ce-4f1a-90e2-8859403d3ecd';

  /**
   * The UUID of the landing page.
   */
  public const LANDING_PAGE = '54acbd6f-47f0-4280-ad09-0a07c397318b';

}
