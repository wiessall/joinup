<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

/**
 * Helper methods for dealing with the search engine.
 */
trait SearchTrait {

  /**
   * Explicitly sent a commit command to the Solr indexes.
   */
  protected function commitSearchIndex(): void {
    $storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    foreach (['published', 'unpublished'] as $index_id) {
      $index = $storage->load($index_id);
      $server = $index->getServerInstance();
      $backend = $server->getBackend();
      /** @var \Drupal\search_api_solr\SolrConnectorInterface $connector */
      $connector = $backend->getSolrConnector();
      $update = $connector->getUpdateQuery();
      $update->addCommit(TRUE, TRUE, TRUE);
      $connector->update($update);
    }
  }

  /**
   * Find all search boxes.
   *
   * @return array
   *   Search box elements.
   */
  protected function findAllSearchBoxes(): array {
    return $this->getSession()
      ->getPage()
      ->findAll('css', '.joinup-navbar .navbar-search input#search-box');
  }

}
