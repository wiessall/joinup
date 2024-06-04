<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Exception;

/**
 * Exception thrown when a distribution mises a parent entity.
 *
 * During normal operation every distribution should have a parent entity, so
 * the only way this exception can occur it because of an unexpected condition
 * occurring at runtime, for example if a data store goes offline.
 *
 * @see \Drupal\joinup_distribution\Entity\AssetDistributionInterface::getParent()
 */
class MissingDistributionParentException extends \RuntimeException {}
