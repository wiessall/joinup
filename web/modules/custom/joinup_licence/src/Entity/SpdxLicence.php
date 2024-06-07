<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Entity;

use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_taxonomy\Entity\MappedTerm;

/**
 * Bundle class for the 'SPDX Licence' vocabulary.
 */
class SpdxLicence extends MappedTerm implements SpdxLicenceInterface {

  use JoinupBundleClassFieldAccessTrait;

  /**
   * {@inheritdoc}
   */
  public function getLicenceId(): ?string {
    if ($id = $this->getMainPropertyValue('spdx_id')) {
      return (string) $id;
    }
    return NULL;
  }

}
