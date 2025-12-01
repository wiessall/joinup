<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\Normalizer\RdfSyncNormalizer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Listens to \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent event.
 */
class RdfSyncNormalizeSubscriber implements EventSubscriberInterface {

  public function __construct(
    #[Autowire(service: 'rdf_sync.normalizer')]
    protected readonly RdfSyncNormalizer $normalizer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [RdfSyncNormalizeEvent::class => 'expandReport'];
  }

  /**
   * Expands the assessment report by adding child entities.
   *
   * The normalized array is enriched by adding triples of child entities
   * regardless of how deep they are in the data structure.
   *
   * Only expand the normalized array when an assessment report canonical page
   * is rendered by using an RDF encoder.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event.
   */
  public function expandReport(RdfSyncNormalizeEvent $event): void {
    if (!$this->isApplicable($event)) {
      return;
    }

    $report = $event->getEntity();
    assert($report instanceof AssessmentReport);

    $normalized = $event->getNormalizedArray();

    // Terms collector. We want to process terms at the end.
    $terms = [];

    // Add the public organisation triples.
    if (!$report->get('provider')->isEmpty()) {
      $publicOrganisation = $report->get('provider')->entity;
      $normalized += $this->normalizer->normalize($publicOrganisation);
      $terms += $this->getEntityTerms($publicOrganisation);
    }

    // Add binding requirement triples.
    $fields = ['expression', 'affects', 'affected_party', 'asset'];
    foreach ($report->get('binding_requirement') as $bindingRequirementField) {
      $bindingRequirement = $bindingRequirementField->entity;
      $normalized += $this->normalizer->normalize($bindingRequirement);

      // Add binding requirement child entities triples.
      foreach ($fields as $fieldName) {
        foreach ($bindingRequirement->get($fieldName) as $field) {
          $entity = $field->entity;
          $normalized += $this->normalizer->normalize($entity);
          $terms += $this->getEntityTerms($entity);
        }
      }
    }

    // Add assessment statements triples.
    $fields = ['legal', 'organisational', 'semantic', 'technical', 'other'];
    foreach ($fields as $fieldName) {
      $field = $report->get($fieldName);
      if (!$field->isEmpty()) {
        $normalized += $this->normalizer->normalize($field->entity);
        $terms += $this->getEntityTerms($field->entity);
      }
    }

    // Finally, add collected terms.
    foreach ($terms as $term) {
      $normalized += $this->normalizer->normalize($term);
    }

    $event->setNormalizedArray($normalized);
  }

  /**
   * Checks whether to subscribe to the given event.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event.
   *
   * @return bool
   *   Whether to subscribe to the given event.
   */
  protected function isApplicable(RdfSyncNormalizeEvent $event): bool {
    $request = $event->getContext()['request'] ?? NULL;
    return $request instanceof Request &&
      $event->getEntity() instanceof AssessmentReport &&
      $request->query->has('_format') &&
      isset(RdfSyncEncoder::getSupportedFormats()[$request->query->get('_format')]);
  }

  /**
   * Extracts a list of taxonomy terms referred by an entity.
   *
   * @param \Drupal\assessments\AssessmentInterface|\Drupal\assessments\Entity\AssessmentStatement $entity
   *   The entity.
   *
   * @return array<int|string, \Drupal\taxonomy\TermInterface>
   *   List of term entities keyed by the term ID.
   */
  protected function getEntityTerms(AssessmentInterface|AssessmentStatement $entity): array {
    $terms = [];

    foreach ($entity as $field) {
      assert($field instanceof FieldItemListInterface);
      $definition = $field->getFieldDefinition()->getItemDefinition();

      if (
        is_a($definition->getClass(), EntityReferenceItemInterface::class, TRUE) &&
        ($targetType = $definition->getSetting('target_type')) &&
        $targetType === 'taxonomy_term'
      ) {
        foreach ($field->referencedEntities() as $term) {
          $terms[$term->id()] = $term;
        }
      }
    }

    return $terms;
  }

}
