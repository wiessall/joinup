<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\EventSubscriber;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\RdfSyncMapper;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of entities for RDF format encoding.
 */
final class DistributionParentRdfSyncSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The mapper service.
   * @param \Drupal\Core\File\FileUrlGeneratorInterface $fileUrlGenerator
   *   The file URL generator service.
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => 'addDistributionParentRelation',
    ];
  }

  /**
   * Adds the dcat:distribution relationship from parent to distribution.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function addDistributionParentRelation(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    $normalizedArray = $event->getNormalizedArray();
    if ($entity instanceof DistributionInterface) {
      // Export all `access_url` values that are external URLs because RDF sync
      // normalizer handles the `target_id` generically assuming that all entity
      // reference fields are handled the same.
      if (!$entity->get('access_url')->isEmpty()) {
        foreach ($entity->get('access_url')->referencedEntities() as $file) {
          $file_url = $file->getFileUri();
          if (UrlHelper::isExternal($file_url) && !UrlHelper::externalIsLocal($file_url, $GLOBALS['base_url'])) {
            $normalizedArray[$entity->getUri()]['http://www.w3.org/ns/dcat#accessURL'][] = (new Resource($file_url))->toRdfPhp();
          }
          else {
            $normalizedArray[$entity->getUri()]['http://www.w3.org/ns/dcat#accessURL'][] = (new Resource($this->fileUrlGenerator->generateAbsoluteString($file_url)))->toRdfPhp();
          }
        }
      }
    }
    $event->setNormalizedArray($normalizedArray);
  }

}
