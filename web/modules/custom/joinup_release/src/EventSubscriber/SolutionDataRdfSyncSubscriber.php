<?php

declare(strict_types=1);

namespace Drupal\joinup_release\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Exception\MissingSolutionException;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use EasyRdf\Literal;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of release entities for RDF format encoding.
 */
final class SolutionDataRdfSyncSubscriber implements EventSubscriberInterface {

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
        ['addSolutionDataToRelease'],
        ['addReleaseDataToSolution'],
      ],
    ];
  }

  /**
   * Adds the parent solution data to the exported data of the release.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addSolutionDataToRelease(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof ReleaseInterface) {
      return;
    }

    try {
      $solution = $entity->getSolution();
    }
    catch (MissingSolutionException) {
      // The parent is missing, so we cannot add the relationship. This can
      // happen during complex operations like entities cleanup where the order
      // of the cleanup can be unpredictable.
      return;
    }

    $normalizedArray = $event->getNormalizedArray();

    $fields = ['owner', 'contact', 'description', 'solution_type'];
    foreach ($fields as $field) {
      $fieldItemList = $solution->get($field);
      if ($fieldItemList->isEmpty()) {
        continue;
      }

      $fieldDefinition = $solution->getFieldDefinition($field);
      $fieldItemList->filterEmptyItems();
      foreach ($fieldItemList as $item) {
        foreach ($fieldDefinition->getThirdPartySettings('rdf_sync') as $property => $mapping) {
          if (empty($mapping['predicate']) || empty($item->$property)) {
            continue;
          }

          if ($fieldItemList instanceof EntityReferenceFieldItemList) {
            $targetEntity = $item->entity;
            $targetId = ($targetEntity instanceof RdfSyncEntityInterface) ? $targetEntity->getUri() : $item->$property;
            $normalizedArray[$entity->getUri()][$mapping['predicate']][] = (new Resource($targetId))->toRdfPhp();
          }
          else {
            $normalizedArray[$entity->getUri()][$mapping['predicate']][] = (new Literal($item->$property))->toRdfPhp();
          }
        }
      }
    }

    $event->setNormalizedArray($normalizedArray);
  }

  /**
   * Updates the hasVersion relationship of the solution with the release.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addReleaseDataToSolution(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof ReleaseInterface) {
      return;
    }

    $data = $event->getNormalizedArray();
    $solution = $entity->getSolution();
    $solutionUri = $solution->getUri();
    $releaseUri = $entity->getUri();

    $graphUri = $this->configFactory->get('rdf_sync.settings')->get('graph_uri');
    $deleteQuery = "WITH <$graphUri> DELETE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> } WHERE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> }";
    $this->sparqlConnection->update($deleteQuery);
    $data[$solutionUri]['http://purl.org/dc/terms/hasVersion'][] = (new Resource($releaseUri))->toRdfPhp();
    $event->setNormalizedArray($data);
  }

}
