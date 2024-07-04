<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Plugin\EntityReferenceSelection;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\Plugin\EntityReferenceSelection\DefaultSelection;

/**
 * Implementation for the distribution parent reference selection.
 *
 * Note that some functionalities of the selection handler will missbehave. For
 * example, the getReferenceableEntities will not return the correct results
 * because it will only look in the RDF storage. However, we do not expose
 * these kind of data anywhere so it is safe to ignore this until solutions are
 * migrated.
 *
 * @todo Remove this selection handler once solutions are migrated into nodes in
 *   ISAICP-8572.
 *
 * @EntityReferenceSelection(
 *   id = "distribution_parent:default",
 *   label = @Translation("Default"),
 *   group = "joinup_distribution",
 *   weight = 0,
 * )
 */
class DistributionParentReferenceSelection extends DefaultSelection {

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableEntities(array $ids): array {
    $result = [];
    if ($ids) {
      $node_ids = array_filter($ids, function ($id) {
        return is_numeric($id);
      });
      $rdf_ids = array_diff($ids, $node_ids);

      $node_query = $this->entityTypeManager->getStorage('node')->getQuery();
      $node_result = empty($node_ids) ? [] : $node_query
        ->accessCheck(FALSE)
        ->condition('nid', $node_ids, 'IN')
        ->execute();

      $rdf_query = $this->entityTypeManager->getStorage('rdf_entity')->getQuery();
      $rdf_result = empty($rdf_ids) ? [] : $rdf_query
        ->accessCheck(FALSE)
        ->condition('rid', $rdf_ids, 'IN')
        ->execute();

      $result = array_merge($node_result, $rdf_result);
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function createNewEntity($entity_type_id, $bundle, $label, $uid): FieldableEntityInterface {
    throw new \RuntimeException('Not supported.');
  }

  /**
   * {@inheritdoc}
   */
  public function validateReferenceableNewEntities(array $entities): array {
    return array_filter($entities, function ($entity) {
      $target_bundles = $this->getConfiguration()['target_bundles'];
      if (isset($target_bundles)) {
        return in_array($entity->bundle(), ['solution', 'release']);
      }
      return TRUE;
    });
  }

}
