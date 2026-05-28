<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Entity;

use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\node\NodeInterface;

/**
 * Interface of certificate content.
 */
interface CertificateInterface extends NodeInterface {

  /**
   * Returns the course the certificate belongs to.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface|null
   *   The course the certificate belongs to. Returns NULL if there is no
   *   connected course yet.
   */
  public function getCourse(): ?SolutionInterface;

}
