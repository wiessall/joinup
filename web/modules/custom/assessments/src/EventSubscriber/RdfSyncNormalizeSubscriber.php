<?php

declare(strict_types=1);

namespace Drupal\assessments\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
use Drupal\Core\Url;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\Assessment\Report;
use Drupal\assessments\Entity\AssessmentStatement;
use Drupal\file\FileInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_sync\Encoder\RdfSyncEncoder;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use Drupal\rdf_sync\Normalizer\RdfSyncNormalizer;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use Drupal\taxonomy\TermInterface;
use EasyRdf\Resource;
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
    protected readonly RdfSyncMapperInterface $mapper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['addDocumentTriples'],
        ['expandReport'],
      ],
    ];
  }

  /**
   * Appends the document triples to the normalized array.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event.
   */
  public function addDocumentTriples(RdfSyncNormalizeEvent $event): void {
    $entity = $event->getEntity();
    if (!$fieldName = $this->getDocumentField($entity)) {
      return;
    }

    $normalized = $event->getNormalizedArray();

    foreach ($entity->get($fieldName) as $item) {
      $file = $item->entity;
      assert($file instanceof FileInterface);
      $fileUrl = Url::fromUserInput($file->get('uri')->url)
        ->setAbsolute()
        ->toString();

      // Reference from entity to the file.
      $normalized[$entity->get('uri')->value]['http://xmlns.com/foaf/0.1/page'][] = (new Resource($fileUrl))->toRdfPhp();
      // The type of the file.
      $normalized[$fileUrl]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][] = (new Resource('http://xmlns.com/foaf/0.1/Document'))->toRdfPhp();
    }

    $event->setNormalizedArray($normalized);
  }

  /**
   * Returns the document field name given an entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return string|null
   *   The name of the document field or NULL.
   */
  protected function getDocumentField(ContentEntityInterface $entity): ?string {
    return match(TRUE) {
      $entity instanceof ParagraphInterface && $entity->bundle() === 'binding_requirement' => 'requirement_documents',
      $entity instanceof AssessmentStatement => 'landing_page',
      default => NULL,
    };
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
    if (!$this->expandReportAllowed($event)) {
      return;
    }

    $report = $event->getEntity();
    assert($report instanceof Report);

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
    $fields = ['expression', 'affects', 'has_participation', 'asset'];
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
    $fields = ['legal', 'organisational', 'semantic', 'technical'];
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
   * Checks whether is possible to expand the report.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event.
   *
   * @return bool
   *   Whether to subscribe to the given event.
   */
  protected function expandReportAllowed(RdfSyncNormalizeEvent $event): bool {
    $request = $event->getContext()['request'] ?? NULL;
    return $request instanceof Request &&
      $event->getEntity() instanceof Report &&
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
  protected function getEntityTerms(AssessmentInterface|AssessmentStatement|SolutionInterface $entity): array {
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
          assert($term instanceof TermInterface);
          if ($this->mapper->isMappedBundle('taxonomy_term', $term->bundle())) {
            $terms[$term->id()] = $term;
          }
        }
      }
    }

    return $terms;
  }

}
