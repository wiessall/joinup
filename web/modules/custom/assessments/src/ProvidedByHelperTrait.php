<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\assessments\Entity\PublicOrganisation;

/**
 * Provides helper methods for working with public organisations.
 */
trait ProvidedByHelperTrait {

  /**
   * The European organisation types that don't have a country.
   */
  protected const array EUROPEAN_ORGANISATION_TYPES = [
    'european_union_institution',
    'european_union_body',
    'european_union_agency',
  ];

  /**
   * Returns the assessment entity storage.
   *
   * @return \Drupal\Core\Entity\ContentEntityStorageInterface
   *   The assessment entity storage.
   */
  abstract protected function getProvidedByAssessmentStorage(): ContentEntityStorageInterface;

  /**
   * Checks if an organisation type machine name is European.
   *
   * @param string $machineName
   *   The organisation type machine name.
   *
   * @return bool
   *   TRUE if the organisation type is European.
   */
  protected function isEuropeanOrganisationType(string $machineName): bool {
    return in_array($machineName, self::EUROPEAN_ORGANISATION_TYPES, TRUE);
  }

  /**
   * Finds an existing public organisation matching the given criteria.
   *
   * Performs case-insensitive name matching to prevent duplicate organisations
   * that differ only in letter casing.
   *
   * @param string $name
   *   The organisation name to search for.
   * @param array $candidateIds
   *   Entity IDs of candidate organisations to check.
   *
   * @return \Drupal\assessments\Entity\PublicOrganisation|null
   *   The matching public organisation, or NULL if not found.
   */
  protected function findOrganisationByName(string $name, array $candidateIds): ?PublicOrganisation {
    if (empty($candidateIds)) {
      return NULL;
    }

    $organisations = $this->getProvidedByAssessmentStorage()->loadMultiple($candidateIds);

    return static::matchOrganisationByName($name, $organisations);
  }

  /**
   * Matches an organisation by name from a list of loaded entities.
   *
   * Performs case-insensitive name matching to prevent duplicate organisations
   * that differ only in letter casing.
   *
   * @param string $name
   *   The organisation name to search for.
   * @param array $organisations
   *   Loaded organisation entities to search through.
   *
   * @return \Drupal\assessments\Entity\PublicOrganisation|null
   *   The matching public organisation, or NULL if not found.
   */
  protected static function matchOrganisationByName(string $name, array $organisations): ?PublicOrganisation {
    if (empty($organisations)) {
      return NULL;
    }

    $searchName = mb_strtolower(trim($name));

    foreach ($organisations as $organisation) {
      if (!$organisation instanceof PublicOrganisation) {
        continue;
      }
      if (mb_strtolower(trim($organisation->label())) === $searchName) {
        return $organisation;
      }
    }

    return NULL;
  }

}
