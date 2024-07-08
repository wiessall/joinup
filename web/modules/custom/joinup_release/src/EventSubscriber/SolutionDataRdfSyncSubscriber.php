<?php

declare(strict_types=1);

namespace Drupal\joinup_release\EventSubscriber;

use Drupal\joinup_distribution\Exception\MissingDistributionParentException;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncMapper;
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
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
    protected SparqlEntityStorageFieldHandlerInterface $fieldHandler,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => 'addSolutionData',
      // @todo Add an event to export the hasVersion value of the solution in
      //   ISAICP-8572 so that the data are not exported now in the wrong graph.
    ];
  }

  /**
   * Adds the parent solution data to the exported data of the release.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addSolutionData(RdfSyncNormalizeEvent $event): void {
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
          $normalizedArray[$entity->getUri()][$predicates['solution']][] = (new Resource($item->$mainPropertyName))->toRdfPhp();
        }
      }
    }

    $event->setNormalizedArray($normalizedArray);
  }

}
