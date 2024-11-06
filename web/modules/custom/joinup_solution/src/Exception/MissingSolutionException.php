<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Exception;

/**
 * Exception thrown when the required reference to a solution is missing.
 *
 * @see \Drupal\joinup_solution\Entity\SolutionContentInterface::getSolution()
 */
class MissingSolutionException extends \Exception {

}
