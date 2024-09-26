<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of solution entities for RDF format encoding.
 */
final class SolutionRdfSyncSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The mapper service.
   * @param \Drupal\rdf_sync\RdfSyncConnectionInterface $sparqlConnection
   *   The SPARQL connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
    protected RdfSyncConnectionInterface $sparqlConnection,
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['addSolutionLinkToCollection'],
      ],
    ];
  }

  /**
   * Adds the relationship to the parent collection (dcat:Catalog dcat:dataset).
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addSolutionLinkToCollection(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof SolutionInterface) {
      return;
    }

    $data = $event->getNormalizedArray();
    $collection = $entity->getCollection();
    $collectionUri = $collection->getUri();
    $solutionUri = $entity->getUri();

    $graphUri = $this->configFactory->get('rdf_sync.settings')->get('graph_uri');
    $deleteQuery = "WITH <$graphUri> DELETE { <$collectionUri> <http://www.w3.org/ns/dcat#dataset> <$solutionUri> } WHERE { <$collectionUri> <http://www.w3.org/ns/dcat#dataset> <$solutionUri> }";
    $this->sparqlConnection->update($deleteQuery);
    $data[$collectionUri]['http://www.w3.org/ns/dcat#dataset'][] = (new Resource($solutionUri))->toRdfPhp();
    $event->setNormalizedArray($data);
  }

}
