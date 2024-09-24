<?php

declare(strict_types=1);

namespace Drupal\eif\EventSubscriber;

use Drupal\eif\Entity\EifPerspectiveInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use EasyRdf\Graph;
use EasyRdf\Literal;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Reacts to EIF perspective meta-entity normalization.
 */
class EifNormalizeSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RdfSyncNormalizeEvent::class => 'onEifPerspectiveNormalize'];
  }

  /**
   * Alters the graph to fit requirements.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The normalize event.
   */
  public function onEifPerspectiveNormalize(RdfSyncNormalizeEvent $event): void {
    $eifPerspective = $event->getEntity();
    if (!$eifPerspective instanceof EifPerspectiveInterface) {
      return;
    }

    if (!$solution = $eifPerspective->getTargetEntity()) {
      throw new \Exception("EIF perspective with ID {$eifPerspective->id()} has no valid target solution");
    }
    $solutionUri = $solution->id();

    $normalized = $event->getNormalizedArray();
    $metaEntityUri = key($normalized);

    // Store recommendations in a var and remove them from the normalized array.
    // Later, they are added back, as blank nodes, to match their structure.
    $recommendationUris = array_column($normalized[$metaEntityUri]['http://data.europa.eu/eif/Recommendation'] ?? [], 'value');
    // Description is optional.
    $recommendationDescriptions = array_pad(array_column($normalized[$metaEntityUri]['http://purl.org/dc/elements/1.1/description'] ?? [], 'value'), count($recommendationUris), '');
    $recommendation = array_combine($recommendationUris, $recommendationDescriptions);
    unset($normalized[$metaEntityUri]['http://data.europa.eu/eif/Recommendation']);
    unset($normalized[$metaEntityUri]['http://purl.org/dc/elements/1.1/description']);

    $graph = new Graph();
    $graph->parse($normalized, 'php');

    // Link the solution.
    $graph->addResource($metaEntityUri, 'http://www.w3.org/ns/adms#aboutAsset', $solutionUri);

    foreach ($recommendation as $uri => $description) {
      $bnodeId = $graph->newBNodeId();
      // Reference to the blank node.
      $graph->addResource($metaEntityUri, 'http://data.europa.eu/eif/Recommendation', $bnodeId);
      $graph->addResource($bnodeId, 'http://data.europa.eu/eif/adms/qualifier', $uri);
      if ($description) {
        $description = new Literal($description, NULL, 'xsd:string');
        $graph->addLiteral($bnodeId, 'http://purl.org/dc/terms/description', $description);
      }
    }

    $event->setNormalizedArray($graph->toRdfPhp());
  }

}
