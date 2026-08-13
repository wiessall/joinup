<?php

declare(strict_types=1);

namespace Drupal\joinup_search;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Utility\Error;
use Drupal\search_api\Utility\PostRequestIndexing;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Makes pending content changes immediately searchable.
 */
class SearchIndexCommitter {

  public function __construct(
    #[Autowire(service: 'search_api.post_request_indexing')]
    protected readonly PostRequestIndexing $postRequestIndexing,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'logger.channel.joinup_search')]
    protected readonly LoggerChannelInterface $logger,
  ) {}

  /**
   * Sends the pending changes to Solr and makes them searchable.
   */
  public function commitPendingChanges(): void {
    $this->postRequestIndexing->destruct();

    $servers = $this->entityTypeManager
      ->getStorage('search_api_server')
      ->loadByProperties([
        'status' => TRUE,
        'backend' => 'search_api_solr',
      ]);

    foreach ($servers as $serverId => $server) {
      try {
        /** @var \Drupal\search_api_solr\SolrBackendInterface $backend */
        $backend = $server->getBackend();
        $connector = $backend->getSolrConnector();
        $commit = $connector->getUpdateQuery();
        $commit->addCommit(softCommit: TRUE, waitSearcher: TRUE);
        $connector->update($commit);
      }
      catch (\Exception $exception) {
        Error::logException($this->logger, $exception, "Can't commit the @server Solr server. Message: @message", [
          '@server' => $serverId,
        ]);
      }
    }
  }

}
