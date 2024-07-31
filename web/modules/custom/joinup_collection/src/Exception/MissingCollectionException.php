<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\Exception;

/**
 * Exception thrown when the required reference to a collection is missing.
 *
 * @see \Drupal\joinup_collection\Entity\CollectionContentInterface::getCollection()
 */
class MissingCollectionException extends \Exception {

}
