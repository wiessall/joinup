<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rdf_sync\Event\RdfSyncNormalizeEvent;
use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to normalization of entities for RDF format encoding.
 */
class NormalizeSubscriber implements EventSubscriberInterface {

  public function __construct(private EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      RdfSyncNormalizeEvent::class => [
        ['normalizeCountries'],
        ['convertKeywords'],
      ],
    ];
  }

  /**
   * Adds additional normalization to country terms.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function normalizeCountries(RdfSyncNormalizeEvent $event): void {
    $term = $event->getEntity();
    if ($term->bundle() !== 'spatial') {
      return;
    }

    $normalizedArray = $event->getNormalizedArray();
    $subject = key($normalizedArray);

    // Add the dc:Location type to countries. Countries are required to be of
    // type dc:Location but are listed as skos:Concept which is also correct.
    // Add the type dc:Location> for each country.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-4566
    $normalizedArray[$subject]['http://www.w3.org/1999/02/22-rdf-syntax-ns#type'][] = (new Resource('http://purl.org/dc/terms/Location'))->toRdfPhp();

    // Move the country code as skos:notation.
    RdfNamespace::set('euvoc', 'http://publications.europa.eu/ontology/euvoc#');
    $code = $term->get('code');
    if (!$code->isEmpty()) {
      $value = new Literal(value: $code->value, datatype: 'http://publications.europa.eu/ontology/euvoc#ISO_3166_1_ALPHA_2');
      $normalizedArray[$subject]['http://www.w3.org/2004/02/skos/core#notation'][] = $value->toRdfPhp();
    }
    // This is a convenience mapping which existed only to map the field.
    unset($normalizedArray[$subject]['http://publications.europa.eu/ontology/euvoc#ISO_3166_1_ALPHA_2']);

    // Add country top concepts.
    $value = $normalizedArray[$subject]['http://publications.europa.eu/resource/authority/country/0005'][0]['value'] ?? NULL;
    $isEuMember = $value === 'true';
    foreach (['', '0001', '0005'] as $item) {
      if ($item === '0005' && !$isEuMember) {
        continue;
      }

      $uri = rtrim('http://publications.europa.eu/resource/authority/country/' . $item, '/');
      $reference = (new Resource($uri))->toRdfPhp();
      $normalizedArray[$subject]['http://www.w3.org/2004/02/skos/core#inScheme'][] = $reference;
      $normalizedArray[$subject]['http://www.w3.org/2004/02/skos/core#topConceptOf'][] = $reference;
    }
    // This is a convenience mapping which existed only to map the field.
    unset($normalizedArray[$subject]['http://publications.europa.eu/resource/authority/country/0005']);

    $event->setNormalizedArray($normalizedArray);
  }

  /**
   * Converts keywords to the text.
   *
   * @param \Drupal\rdf_sync\Event\RdfSyncNormalizeEvent $event
   *   The event object.
   */
  public function convertKeywords(RdfSyncNormalizeEvent $event): void {
    $normalizedArray = $event->getNormalizedArray();
    $subject = key($normalizedArray);
    if (isset($normalizedArray[$subject]['http://www.w3.org/ns/dcat#keyword'])) {
      foreach ($normalizedArray[$subject]['http://www.w3.org/ns/dcat#keyword'] as $key => $value) {
        if (is_array($value) && isset($value['value'])) {
          $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($value['value']);
          if ($term) {
            $normalizedArray[$subject]['http://www.w3.org/ns/dcat#keyword'][$key] = [
              'value' => $term->getName(),
              'language' => $term->language()->getId(),
              'datatype' => 'http://www.w3.org/2001/XMLSchema#string',
              'type' => 'literal',
            ];
          }
        }
      }
      $event->setNormalizedArray($normalizedArray);
    }
  }

}
