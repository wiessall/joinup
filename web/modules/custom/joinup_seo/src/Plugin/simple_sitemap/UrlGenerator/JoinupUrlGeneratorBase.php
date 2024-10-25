<?php

declare(strict_types=1);

namespace Drupal\joinup_seo\Plugin\simple_sitemap\UrlGenerator;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\joinup_group\JoinupGroupHelper;
use Drupal\simple_sitemap\Exception\SkipElementException;
use Drupal\simple_sitemap\Plugin\simple_sitemap\UrlGenerator\EntityUrlGenerator;

/**
 * Base class for Portal Url Generators.
 */
abstract class JoinupUrlGeneratorBase extends EntityUrlGenerator {

  /**
   * Returns an array of datasets to be processed.
   *
   * @return array
   *   An array of arrays, each of which contains the entity key and entity id.
   */
  public function getDataSets(): array {
    $data_sets = [];
    $sitemap_entity_types = $this->entityHelper->getSupportedEntityTypes();
    $all_bundle_settings = $this->entitiesManager->setSitemaps($this->sitemap->id())->getAllBundleSettings();
    if (isset($all_bundle_settings[$this->sitemap->id()])) {
      foreach ($all_bundle_settings[$this->sitemap->id()] as $entity_type_name => $bundles) {
        if (!isset($sitemap_entity_types[$entity_type_name])) {
          continue;
        }

        if ($this->isOverwrittenForEntityType($entity_type_name)) {
          continue;
        }

        $entityTypeStorage = $this->entityTypeManager->getStorage($entity_type_name);
        $keys = $sitemap_entity_types[$entity_type_name]->getKeys();

        foreach ($bundles as $bundle_name => $bundle_settings) {
          if ($bundle_settings['index']) {
            $query = $entityTypeStorage->getQuery();

            if (!empty($keys['id'])) {
              $query->sort($keys['id'], 'ASC');
            }
            if (!empty($keys['bundle'])) {
              $query->condition($keys['bundle'], $bundle_name);
            }
            if (!empty($keys['published'])) {
              $query->condition($keys['published'], 1);
            }
            elseif (!empty($keys['status'])) {
              $query->condition($keys['status'], 1);
            }

            // Shift access check to EntityUrlGeneratorBase for language
            // specific access.
            // See https://www.drupal.org/project/simple_sitemap/issues/3102450.
            $query->accessCheck(FALSE);

            // Mainly this is the only line we need but we need to override the
            // whole method.
            $this->alterQuery($query, $entity_type_name, $bundle_name, $bundle_settings);

            $data_set = [
              'entity_type' => $entity_type_name,
              'id' => [],
            ];
            foreach ($query->execute() as $entity_id) {
              $data_set['id'][] = $entity_id;
              if (count($data_set['id']) >= $this->entitiesPerDataset) {
                $data_sets[] = $data_set;
                $data_set['id'] = [];
              }
            }
            // Add the last data set if there are some IDs gathered.
            if (!empty($data_set['id'])) {
              $data_sets[] = $data_set;
            }
          }
        }
      }
    }

    return $data_sets;
  }

  /**
   * {@inheritdoc}
   */
  protected function processDataSet($data_set): array {
    foreach ($this->entityTypeManager->getStorage($data_set['entity_type'])->loadMultiple((array) $data_set['id']) as $entity) {
      // In case the entity type is a node, we also need to also take into
      // account the status of the parent.
      if ($entity->getEntityTypeId() === 'node') {
        $parent = JoinupGroupHelper::getGroup($entity);
        if (empty($parent) || !$parent->isPublished()) {
          continue;
        }
      }
      try {
        $paths[] = $this->processEntity($entity);
      }
      catch (SkipElementException $e) {
        continue;
      }
    }

    return $paths ?? [];
  }

  /**
   * Alters the query that is responsible for fetching entities for the sitemap.
   *
   * @param \Drupal\Core\Entity\Query\QueryInterface $query
   *   The query object before being executed.
   * @param string $entity_type_id
   *   The entity type id of the query.
   * @param string $bundle
   *   The entity bundle passed in the query.
   * @param array $bundle_settings
   *   The sitemap bundle settings.
   */
  abstract public function alterQuery(QueryInterface $query, string $entity_type_id, string $bundle, array $bundle_settings): void;

}
