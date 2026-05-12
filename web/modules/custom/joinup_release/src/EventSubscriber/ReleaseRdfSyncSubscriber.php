<?php

declare(strict_types=1);

namespace Drupal\joinup_release\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\file\FileInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_solution\Exception\MissingSolutionException;
use Drupal\link\LinkItemInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncConnectionInterface;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use EasyRdf\Literal;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of release entities for RDF format encoding.
 */
final readonly class ReleaseRdfSyncSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected RdfSyncMapperInterface $mapper,
    protected RdfSyncConnectionInterface $sparqlConnection,
    protected ConfigFactoryInterface $configFactory,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['addSolutionDataToRelease'],
        ['addResourcesToRelease', 20],
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

    $fields = ['owner', 'contact', 'description', 'solution_type', 'language'];
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
   * Adds resources linked to the release to the exported data of the release.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addResourcesToRelease(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$entity instanceof ReleaseInterface) {
      return;
    }

    if ($entity->get('resources')->isEmpty()) {
      return;
    }

    $normalizedArray = $event->getNormalizedArray();
    foreach ($entity->getResources() as $resource) {
      if ($resource instanceof LinkItemInterface) {
        $normalizedArray[$entity->getUri()]['http://xmlns.com/foaf/0.1/page'][] = (new Resource($resource->uri))->toRdfPhp();
      }
      elseif ($resource instanceof FileInterface) {
        $normalizedArray[$entity->getUri()]['http://xmlns.com/foaf/0.1/page'][] = (new Resource($this->fileUrlGenerator->generateAbsoluteString($resource->getFileUri())))->toRdfPhp();
      }
    }
  }

}
