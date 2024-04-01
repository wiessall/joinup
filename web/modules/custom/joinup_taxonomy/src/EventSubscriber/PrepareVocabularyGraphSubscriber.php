<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\EventSubscriber;

use Drupal\Component\Serialization\Json;
use Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent;
use EasyRdf\Literal;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Listens to graph preparation event.
 */
class PrepareVocabularyGraphSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PrepareVocabularyGraphEvent::class => [
        ['removeNonEnglishVersion', 10],
        ['alterLanguage'],
        ['alterSpatial'],
        ['alterFileType'],
        ['alterOwnerType'],
        ['alterEira'],
      ],
    ];
  }

  /**
   * Removes any non-English version of the taxonomy terms.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function removeNonEnglishVersion(PrepareVocabularyGraphEvent $event): void {
    $graph = $event->getGraph();
    foreach ($graph->resources() as $resource) {
      foreach ($resource->propertyUris() as $propertyUri) {
        // Remove any non-English version of the taxonomy terms since we are
        // only supporting an English version on the website. If more terms are
        // needed, the supported languages should be extended (the "en" below).
        // This needs to repeat multiple times as the terms might.
        $predicate = RdfNamespace::shorten($propertyUri) ?? $propertyUri;
        foreach ($resource->allLiterals($predicate) as $literal) {
          $lang = $literal->getLang();
          if ($lang && $lang !== 'en') {
            $graph->deleteLiteral($resource, new Resource($propertyUri), $literal, $lang);
          }
        }
      }
    }
  }

  /**
   * Alters the graph for the language vocabulary.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function alterLanguage(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid !== 'language') {
      return;
    }
    $graph = $event->getGraph();

    foreach ($graph->resources() as $resource) {
      // If it's not a "skos:Concept", then it's not a language vocabulary term.
      // Remove this resource and continue with the next one.
      if ($resource->type() !== 'skos:Concept') {
        $graph->deleteEntireResource($resource);
        continue;
      }

      // This resource has no labels. Remove it and continue with the next one.
      if (!$objects = $graph->allLiterals($resource, 'skos:prefLabel')) {
        $graph->deleteEntireResource($resource);
        continue;
      }

      // We get our languages from the Metadata Registry. The Metadata Registry
      // maintains two authority tables, one for individual languages and one
      // for groups of languages called multilingual. For legacy reasons, the
      // two tables are published as a merger of the two. The multilingual
      // language groups are not useful for us, and we need to filter them out
      // to avoid having the language lists polluted with entries labeled
      // 'Multilingual Code'.
      // @see http://publications.europa.eu/mdr/resource//documentation/schema/cat.html#element_languages
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-2764
      foreach ($objects as $object) {
        if ($object->getValue() === 'Multilingual Code') {
          $graph->deleteEntireResource($resource);
          // Pickup next resource.
          break;
        }
      }
    }
  }

  /**
   * Alters the graph for the spatial vocabulary.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function alterSpatial(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid === 'spatial') {
      $graph = $event->getGraph();
      // Remove deprecated countries from the country list. See ISAICP-3442.
      foreach ($graph->resources() as $resource) {
        if ($graph->hasProperty($resource, 'http://publications.europa.eu/ontology/authority/end.use')) {
          $graph->deleteEntireResource($resource);
          continue;
        }
        $graph->addType($resource, 'http://purl.org/dc/terms/Location');
      }
    }
  }

  /**
   * Alters the graph for the file_type vocabulary.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function alterFileType(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid !== 'file_type') {
      return;
    }
    $graph = $event->getGraph();

    foreach ($graph->resources() as $resource) {
      if ($resource->type() === 'skos:Concept') {
        $mappedCode = $resource->allResources(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/op-mapped-code', TRUE));
        $extensions = [];
        $mimeType = NULL;
        /** @var \EasyRdf\Resource $reference */
        foreach ($mappedCode as $reference) {
          if ($reference->hasProperty('http://purl.org/dc/elements/1.1/source', 'file-extension')) {
            // A list of extensions separated by space might be provided.
            $extension = preg_split('/\s/', trim($reference->getLiteral(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/legacy-code', TRUE))->getValue()));
            $extensions = array_unique([...$extensions, ...$extension]);
          }
          if ($reference->hasProperty('http://purl.org/dc/elements/1.1/source', 'manifestation-type-cellar')) {
            $mimeType ??= trim($reference->getLiteral(RdfNamespace::shorten('http://publications.europa.eu/ontology/authority/legacy-code', TRUE))->getValue());
          }
        }
        if ($mimeType) {
          $graph->addLiteral($resource, 'http://joinup.eu/file-type/mime-type', $mimeType);
        }
        if ($extensions) {
          $graph->addLiteral($resource, RdfNamespace::shorten('http://joinup.eu/file-type/extension', TRUE), $extensions);
        }
      }
    }
  }

  /**
   * Alters the graph for the owner_type vocabulary.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function alterOwnerType(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid === 'owner_type') {
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3084
      $event->getGraph()->addResource(
        'http://purl.org/adms/licencetype/ViralEffect-ShareAlike',
        'http://www.w3.org/2004/02/skos/core#inScheme',
        'http://purl.org/adms/licencetype/1.1',
      );
    }
  }

  /**
   * Alters the graph for the eira vocabulary.
   *
   * @param \Drupal\joinup_taxonomy\Event\PrepareVocabularyGraphEvent $event
   *   The event.
   */
  public function alterEira(PrepareVocabularyGraphEvent $event): void {
    if ($event->vid !== 'eira') {
      return;
    }
    $graph = $event->getGraph();

    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

    foreach ($graph->resources() as $resource) {
      if (str_ends_with($resource->getUri(), '_Properties')) {
        // For EIRA_SKOS, after version 6, they have added all extra properties
        // of the EIRA terms as separate terms in the graph. To properly handle
        // these secondary terms in the website, we extract the extra-properties
        // and inject them, as a Json serialized array, into the main term.
        $namespaces = $rows = [];
        $mainResource = $resource->getResource('skos:related');
        foreach ($resource->propertyUris() as $propertyUri) {
          $shortenedProperty = RdfNamespace::shorten($propertyUri);
          // Do not store the skos:topConceptOf and skos:related properties.
          if (in_array($shortenedProperty, ['skos:topConceptOf', 'skos:related'], TRUE)) {
            continue;
          }
          $prefix = RdfNamespace::prefixOfUri($propertyUri);
          $namespaces[$prefix] = [
            'namespace' => $prefix . ':',
            'uri' => RdfNamespace::get($prefix),
          ];
          foreach ($graph->all($resource, $shortenedProperty) as $object) {
            $rows[] = [
              'property' => $shortenedProperty,
              'value' => $object->toRdfPhp(),
            ];
          }
        }

        // Inject properties in the main term.
        $graph->addLiteral($mainResource, 'http://joinup.eu/eira/properties', Json::encode([
          'namespaces' => $namespaces,
          'rows' => $rows,
        ]));
        $graph->deleteSingleProperty($mainResource, $resource->getUri());

        // We've extracted what we need. Drop this resource and jump to next.
        $graph->deleteEntireResource($resource);
        continue;
      }

      if ($resource->isA('skos:Collection')) {
        // Add the skos:Concept type to all collection elements so that they are
        // listed as parent terms.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3216
        $graph->addType($resource, 'skos:Concept');

        foreach ($graph->allResources($resource, 'skos:member') as $member) {
          // Create a backwards connection from the children to the parent.
          $graph->addResource($member, 'skos:broaderTransitive', $resource);
        }
      }

      // Add the link to all skos:Concept type elements so that they are all
      // considered as children of the vocabulary regardless of their depth.
      if ($resource->isA('skos:Concept')) {
        $graph->addResource($resource, 'skos:topConceptOf', 'http://data.europa.eu/dr8');
      }

      // Fix resources with multiple labels or descriptions. Actually, these
      // resources are defined multiple times in EIRA_SKOS.rdf file which is a
      // bug. We're keeping the values currently used in production.
      switch ($resource->getUri()) {
        case 'http://data.europa.eu/dr8/ArchitecturePrinciple':
          $this->deleteLiteralContainingText($resource, 'skos:prefLabel', '<<ELAP:Architecture Principle>> Privacy', TRUE);
          $this->deleteLiteralContainingText($resource, 'skos:prefLabel', '<<ELAP:Architecture Principle>> Security', TRUE);
          break;

        case 'http://data.europa.eu/dr8/AuthenticationMiddlewareApplicationComponent':
          $this->deleteLiteralContainingText($resource, 'skos:prefLabel', 'Authentication Middleware', TRUE);
          $this->deleteLiteralContainingText($resource, 'skos:definition', 'Source: Inspired in Gartner');
          break;

        case 'http://data.europa.eu/dr8/AdministrativeSimplification':
          $this->deleteLiteralContainingText($resource, 'skos:definition', 'Harmonised rules on eligibility and durability');
          break;

        case 'http://data.europa.eu/dr8/ClientrequestforserviceApplicationEvent':
          $this->deleteLiteralContainingText($resource, 'skos:definition', 'https://www.techtarget.com/searchenterprisedesktop/definition/client');
          break;

        case 'http://data.europa.eu/dr8/HostingFacility':
          $this->deleteLiteralContainingText($resource, 'skos:definition', 'Source: DIGIT C Infrastructure Services Provision');
      }
    }
  }

  /**
   * Deletes a triple whose object is a literal containing a given text.
   *
   * @param \EasyRdf\Resource $resource
   *   The resource.
   * @param string $predicate
   *   The predicate.
   * @param string $text
   *   The text to be checked for.
   * @param bool $strict
   *   (optional) Whether to perform a full text match or only a partial one.
   */
  protected function deleteLiteralContainingText(Resource $resource, string $predicate, string $text, bool $strict = FALSE): void {
    foreach ($resource->getGraph()->allLiterals($resource, $predicate) as $object) {
      assert($object instanceof Literal);
      $match = $strict ? ($object->getValue() === $text) : str_contains($object->getValue(), $text);
      if ($match) {
        $resource->getGraph()->deleteLiteral($resource, $predicate, $object);
      }
    }
  }

}
