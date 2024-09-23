<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Entity;

use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for licence nodes.
 */
interface LicenceInterface extends NodeInterface, RdfSyncEntityInterface, DescriptionInterface, PermalinkInterface {

  /**
   * Returns the legal types to which this licence conforms.
   *
   * @return \Drupal\joinup_licence\Entity\LicenceLegalTypeInterface[]
   *   The legal types.
   */
  public function getLegalTypes(): array;

  /**
   * Returns whether the licence conforms to the given legal type.
   *
   * @param string $category_label
   *   The legal type category label, such as 'Can', 'Must', 'Cannot', etc.
   * @param string $label
   *   The legal type label, such as 'Use/reproduce', 'Distribute', etc.
   *
   * @return bool
   *   Whether the licence conforms to the legal type.
   */
  public function hasLegalType(string $category_label, string $label): bool;

  /**
   * Returns the associated SPDX licence term entity.
   *
   * @return \Drupal\joinup_licence\Entity\SpdxLicenceInterface|null
   *   The SPDX licence term, or NULL if none is associated.
   */
  public function getSpdxLicenceEntity(): ?SpdxLicenceInterface;

  /**
   * Returns the ID of the associated SPDX licence, such as 'Apache-2.0'.
   *
   * @return string|null
   *   The ID of the SPDX licence, or NULL if no SPDX licence is associated.
   */
  public function getSpdxLicenceId(): ?string;

  /**
   * Returns the URL the associated SPDX licence.
   *
   * @return string|null
   *   The URL of the SPDX licence, or NULL if no SPDX licence is associated.
   */
  public function getSpdxLicenceUrl(): ?string;

  /**
   * Returns a document ID that details how the licence can be redistributed.
   *
   * This document contains advice how code or data which is distributed under
   * the current licence can be used in a project which is going to be
   * distributed under the passed in licence.
   *
   * @param \Drupal\joinup_licence\Entity\LicenceInterface $outbound_licence
   *   The licence under which the current code or data is going to be
   *   redistributed.
   *
   * @return string
   *   The document ID of the compatibility document that contains the requested
   *   information. If the licences are not compatible the ID "INCOMPATIBLE" is
   *   returned, which is the ID of the document explaining the licences are
   *   incompatible.
   */
  public function getCompatibilityDocumentId(LicenceInterface $outbound_licence): string;

  /**
   * Returns the document that details how the licence can be redistributed.
   *
   * This document contains advice how (and if) code or data which is
   * distributed under the current licence can be used in a project which is
   * going to be distributed under the passed in licence.
   *
   * @param \Drupal\joinup_licence\Entity\LicenceInterface $outbound_licence
   *   The licence under which the current code or data is going to be
   *   redistributed.
   *
   * @return \Drupal\joinup_licence\Entity\CompatibilityDocumentInterface
   *   The compatibility document that contains the requested information.
   */
  public function getCompatibilityDocument(LicenceInterface $outbound_licence): CompatibilityDocumentInterface;

  /**
   * Returns the Licence entity that corresponds to the given SPDX ID.
   *
   * @param string $spdx_id
   *   The SPDX ID for which to return the corresponding Licence entity.
   *
   * @return \Drupal\joinup_licence\Entity\LicenceInterface|null
   *   The licence entity, or NULL if no licence corresponds to the given ID.
   */
  public static function loadBySpdxId(string $spdx_id): ?LicenceInterface;

}
