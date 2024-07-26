<?php

declare(strict_types=1);

namespace Drupal\joinup_release\EventSubscriber;

use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\joinup_distribution\Exception\MissingDistributionParentException;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface;
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
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface $fieldHandler
   *   The field handler service.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparqlConnection
   *   The SPARQL connection.
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
    protected SparqlEntityStorageFieldHandlerInterface $fieldHandler,
    // @todo Drop this parameter in ISAICP-8572.
    protected ConnectionInterface $sparqlConnection,
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
    $normalizedArray = $event->getNormalizedArray();
    if (!$entity instanceof ReleaseInterface) {
      return;
    }

    try {
      $solution = $entity->getSolution();
    }
    catch (MissingDistributionParentException $e) {
      // The parent is missing, so we cannot add the relationship. This can
      // happen during during complex operations like entities cleanup where
      // the order of the cleanup can be unpredictable.
      return;
    }

    $fields = [
      'field_is_owner',
      'field_is_contact_information',
      'field_is_description',
      'field_is_solution_type',
    ];
    foreach ($fields as $field) {
      if ($solution->get($field)->isEmpty()) {
        continue;
      }
      // @todo Change the predicate calculation from the field storage to the
      //   field third party settings once solutions are nodes in ISAICP-8572.
      $storageDefinition = $solution->getFieldDefinition($field)->getFieldStorageDefinition();
      $mainPropertyName = $storageDefinition->getMainPropertyName();
      $predicates = $this->fieldHandler->getFieldPredicates($solution->getEntityTypeId(), $field, $mainPropertyName, 'solution');

      $fieldItemList = $solution->get($field);
      $fieldItemList->filterEmptyItems();
      foreach ($fieldItemList as $item) {
        if ($mainPropertyName === 'value') {
          $normalizedArray[$entity->getUri()][$predicates['solution']][] = (new Literal($item->$mainPropertyName))->toRdfPhp();
        }
        else {
          if (
            ($fieldItemList instanceof EntityReferenceFieldItemList)
            && ($targetEntity = $item->entity)
            && $targetEntity instanceof RdfSyncEntityInterface
          ) {
            $targetId = $targetEntity->getUri();
          }
          else {
            $targetId = $item->$mainPropertyName;
          }
          $normalizedArray[$entity->getUri()][$predicates['solution']][] = (new Resource($targetId))->toRdfPhp();
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

    $solution = $entity->getSolution();
    $solutionUri = $solution->id();
    $releaseUri = $entity->getUri();

    foreach (['default', 'draft'] as $graph) {
      if ($solution->hasGraph($graph)) {
        $graph = $graph === 'default' ? 'http://joinup.eu/solution/published' : 'http://joinup.eu/solution/draft';
        $deleteQuery = "WITH <$graph> DELETE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> } WHERE { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> }";
        $this->sparqlConnection->update($deleteQuery);
        $insertQuery = "WITH <$graph> INSERT { <$solutionUri> <http://purl.org/dc/terms/hasVersion> <$releaseUri> }";
        $this->sparqlConnection->update($insertQuery);
      }
    }
  }

}
