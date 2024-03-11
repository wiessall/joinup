<?php

declare(strict_types=1);

namespace Drupal\joinup_licence\Entity;

use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\node\Entity\Node;

/**
 * Bundle class for the 'licence' bundle.
 */
class Licence extends Node implements LicenceInterface {

  use DescriptionTrait;
  use JoinupBundleClassFieldAccessTrait;
  use RdfSyncEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getLegalTypes(): array {
    return $this->getReferencedEntities('legal_type');
  }

  /**
   * {@inheritdoc}
   */
  public function hasLegalType(string $category_label, string $label): bool {
    foreach ($this->getLegalTypes() as $legal_type) {
      if ($legal_type->label() === $label) {
        if ($category = $legal_type->getCategory()) {
          if ($category->label() === $category_label) {
            return TRUE;
          }
        }
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpdxLicenceEntity(): ?SpdxLicenceInterface {
    return $this->get('spdx_licence')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpdxLicenceId(): ?string {
    if ($spdx_licence = $this->getSpdxLicenceEntity()) {
      return $spdx_licence->getLicenceId();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSpdxLicenceUrl(): ?string {
    if ($spdx = $this->getSpdxLicenceEntity()) {
      return $spdx->getUri();
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCompatibilityDocumentId(LicenceInterface $outbound_licence): string {
    /** @var \Drupal\joinup_licence\JoinupLicenceCompatibilityRulePluginManager $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.joinup_licence_compatibility_rule');
    return $plugin_manager->getCompatibilityDocumentId($this, $outbound_licence);
  }

  /**
   * {@inheritdoc}
   */
  public function getCompatibilityDocument(LicenceInterface $outbound_licence): CompatibilityDocumentInterface {
    $compatibility_document_id = $this->getCompatibilityDocumentId($outbound_licence);
    return CompatibilityDocument::load($compatibility_document_id)
      ->setUseLicence($this)
      ->setRedistributeAsLicence($outbound_licence);
  }

  /**
   * {@inheritdoc}
   */
  public static function loadBySpdxId(string $spdx_id): ?LicenceInterface {
    $tids = \Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
      ->condition('vid', 'spdx_term')
      ->condition('spdx_id', $spdx_id)
      ->execute();

    if (!$tids) {
      return NULL;
    }

    /** @var \Drupal\joinup_licence\Entity\LicenceInterface $licence */
    $licence = \Drupal::entityTypeManager()->getStorage('node')->load(reset($tids));

    return $licence;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'description';
  }

}
