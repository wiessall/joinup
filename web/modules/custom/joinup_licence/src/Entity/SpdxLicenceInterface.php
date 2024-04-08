<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Entity;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;

/**
 * Interface for SPDX licence terms.
 */
interface SpdxLicenceInterface extends RdfSyncEntityInterface {

  /**
   * Returns the SPDX licence ID, such as 'Apache-2.0'.
   *
   * @return string|null
   *   The SPDX licence ID, or NULL if it is not set.
   */
  public function getLicenceId(): ?string;

}
