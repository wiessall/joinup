<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\EntityReferenceSelection;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\Attribute\EntityReferenceSelection;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Provides a selection handler for solutions prioritizing IES solutions.
 *
 * This selection handler displays Interoperable Europe Solutions (IES) at the
 * top of autocomplete results, followed by other solutions sorted by title.
 */
#[EntityReferenceSelection(
  id: "ies_solution",
  label: new TranslatableMarkup("Interoperable Europe Solution selection"),
  group: "ies_solution",
  weight: 0,
  entity_types: ["node"]
)]
class IesSolutionSelection extends DefaultSelection {

  /**
   * The IES collection node ID (cached).
   *
   * @var string|null
   */
  protected ?string $iesCollectionId = NULL;

  /**
   * {@inheritdoc}
   */
  public function getReferenceableEntities($match = NULL, $match_operator = 'CONTAINS', $limit = 0): array {
    $ies_collection_id = $this->getIesCollectionId();

    // If no IES collection exists, fall back to default behavior.
    if (!$ies_collection_id) {
      return parent::getReferenceableEntities($match, $match_operator, $limit);
    }

    $options = [];
    $target_type = $this->getConfiguration()['target_type'];

    // Get IES solutions first.
    $ies_query = $this->buildEntityQuery($match, $match_operator);
    $ies_query->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $ies_collection_id);
    if ($limit > 0) {
      $ies_query->range(0, $limit);
    }
    $ies_result = $ies_query->execute();

    if (!empty($ies_result)) {
      $ies_entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($ies_result);
      foreach ($ies_entities as $entity_id => $entity) {
        $bundle = $entity->bundle();
        $options[$bundle][$entity_id] = Html::escape($this->entityRepository->getTranslationFromContext($entity)->label() ?? '');
      }
    }

    // Calculate remaining limit for non-IES solutions.
    $ies_count = count($ies_result);
    $remaining_limit = $limit > 0 ? max(0, $limit - $ies_count) : 0;

    // Get non-IES solutions if we haven't reached the limit.
    if ($limit === 0 || $remaining_limit > 0) {
      $non_ies_query = $this->buildEntityQuery($match, $match_operator);

      // Exclude solutions that belong to the IES collection.
      // Include solutions with no collection (empty og_audience) OR
      // solutions belonging to other collections.
      $group = $non_ies_query->orConditionGroup()
        ->notExists(OgGroupAudienceHelperInterface::DEFAULT_FIELD)
        ->condition(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $ies_collection_id, '<>');
      $non_ies_query->condition($group);

      // Exclude already found IES solutions.
      if (!empty($ies_result)) {
        $non_ies_query->condition('nid', array_keys($ies_result), 'NOT IN');
      }

      if ($remaining_limit > 0) {
        $non_ies_query->range(0, $remaining_limit);
      }

      $non_ies_result = $non_ies_query->execute();

      if (!empty($non_ies_result)) {
        $non_ies_entities = $this->entityTypeManager->getStorage($target_type)->loadMultiple($non_ies_result);
        foreach ($non_ies_entities as $entity_id => $entity) {
          $bundle = $entity->bundle();
          $options[$bundle][$entity_id] = Html::escape($this->entityRepository->getTranslationFromContext($entity)->label() ?? '');
        }
      }
    }

    return $options;
  }

  /**
   * Gets the Interoperable Europe collection node ID.
   */
  protected function getIesCollectionId(): ?string {
    if ($this->iesCollectionId === NULL) {
      $collection = $this->entityRepository->loadEntityByUuid(
        'node',
        InteroperableEuropeCollectionInterface::COLLECTION_UUID
      );
      $this->iesCollectionId = (string) $collection?->id();
    }

    return $this->iesCollectionId ?: NULL;
  }

}
