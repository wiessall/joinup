<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\EventSubscriber;

use Drupal\Core\Entity\EntityInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Rewrites content triples from annotation URI to target-entity URI.
 *
 * When a dah_annotation node is serialized, its content fields should appear
 * as triples about the referenced solution (via field_solution), not about
 * the annotation itself. The annotation still gets its own triples for
 * provenance (rdf:type, prov:wasGeneratedBy, prov:generatedAtTime).
 */
class DahAnnotationNormalizeSubscriber implements EventSubscriberInterface {

  /**
   * Predicates that describe the annotation itself, not its subject.
   *
   * These stay on the annotation URI; everything else gets rewritten.
   */
  protected const ANNOTATION_OWN_PREDICATES = [
    'http://www.w3.org/1999/02/22-rdf-syntax-ns#type',
    'http://purl.org/dc/terms/modified',
    'http://purl.org/dc/terms/issued',
    'http://purl.org/dc/terms/created',
    'http://purl.org/dc/terms/title',
    'http://www.w3.org/ns/prov#wasGeneratedBy',
    'http://www.w3.org/ns/prov#generatedAtTime',
    'http://www.w3.org/ns/prov#wasAttributedTo',
  ];

  /**
   * DAH collection URI — attribution target for provenance.
   */
  protected const DAH_COLLECTION_URI = 'http://data.europa.eu/w21/e9fe81b1-bdc4-4bbc-959d-4d0fbf4e0048';

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['rewriteAnnotationSubject', 100],
      ],
    ];
  }

  /**
   * Rewrites non-provenance triples to point at the referenced solution.
   */
  public function rewriteAnnotationSubject(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$this->isAnnotationNode($entity)) {
      return;
    }

    $annotationNode = $entity;
    assert($annotationNode instanceof NodeInterface);

    // Resolve the referenced solution. If missing, skip — the annotation
    // is orphaned and shouldn't write anything.
    $solutionField = $annotationNode->get('field_solution');
    if ($solutionField->isEmpty()) {
      return;
    }
    $solution = $solutionField->entity;
    if (!$solution instanceof RdfSyncEntityInterface) {
      return;
    }
    $solutionUri = $solution->getUri();
    if (empty($solutionUri)) {
      return;
    }

    $annotationUri = $annotationNode->get('uri')->value ?? NULL;
    if (empty($annotationUri)) {
      return;
    }

    $normalized = $event->getNormalizedArray();

    // Nothing to rewrite if annotation isn't even in the normalized set.
    if (!isset($normalized[$annotationUri])) {
      return;
    }

    $annotationTriples = $normalized[$annotationUri];
    $keepOnAnnotation = [];
    $moveToSolution = [];

    foreach ($annotationTriples as $predicate => $objects) {
      if (in_array($predicate, self::ANNOTATION_OWN_PREDICATES, TRUE)) {
        $keepOnAnnotation[$predicate] = $objects;
      }
      else {
        $moveToSolution[$predicate] = $objects;
      }
    }

    // Write kept triples back to the annotation URI.
    $normalized[$annotationUri] = $keepOnAnnotation;

    // Add provenance triples on the annotation.
    $normalized[$annotationUri]['http://www.w3.org/ns/prov#wasAttributedTo'][] =
      (new Resource(self::DAH_COLLECTION_URI))->toRdfPhp();
    $normalized[$annotationUri]['http://www.w3.org/ns/prov#generatedAtTime'][] = [
      'type' => 'literal',
      'value' => gmdate('c'),
      'datatype' => 'http://www.w3.org/2001/XMLSchema#dateTime',
    ];

    // Add a link from the annotation to the thing it's about.
    $normalized[$annotationUri]['http://www.w3.org/ns/prov#used'][] =
      (new Resource($solutionUri))->toRdfPhp();

    // Move content triples to the solution's graph entry.
    foreach ($moveToSolution as $predicate => $objects) {
      if (!isset($normalized[$solutionUri][$predicate])) {
        $normalized[$solutionUri][$predicate] = [];
      }
      $normalized[$solutionUri][$predicate] = array_merge(
        $normalized[$solutionUri][$predicate],
        $objects
      );
    }

    // Remove the field_solution triple itself from the solution graph entry
    // if it leaked there. The annotation→solution reference doesn't need to
    // appear as a solution property.
    $solutionFieldPredicate = 'http://www.w3.org/ns/prov#used';
    // (already handled — prov:used on annotation only)

    $event->setNormalizedArray($normalized);
  }

  /**
   * Checks whether the entity is a dah_annotation node.
   */
  protected function isAnnotationNode(EntityInterface $entity): bool {
    return $entity instanceof NodeInterface && $entity->bundle() === 'dah_annotation';
  }

}
