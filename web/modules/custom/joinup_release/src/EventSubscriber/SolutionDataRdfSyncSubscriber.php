<?php

declare(strict_types=1);

namespace Drupal\joinup_release\EventSubscriber;

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
 * Listens to normalization of entities for RDF format encoding.
 */
final class SolutionDataRdfSyncSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The mapper service.
   * @param \Drupal\rdf_sync\RdfSyncConnectionInterface $sparqlConnection
   *   The SPARQL connection.
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
    protected RdfSyncConnectionInterface $sparqlConnection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['addSolutionDataToRelease'],
        // @todo Change this accordingly in ISAICP-8572.
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
   *
   * @todo Use the normalizer to properly pass the data in ISAICP-8572.
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

    // @todo Should we be getting the graph from the settings?
    $deleteQuery = "WITH <http://data> DELETE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> } WHERE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> }";
    $this->sparqlConnection->update($deleteQuery);
    // @todo Check if passing the solution as a single property, deletes all
    //   other properties.
    $data[$solutionUri]['http://purl.org/dc/terms/hasVersion'][] = (new Resource($releaseUri))->toRdfPhp();
    $event->setNormalizedArray($data);
  }

}
