<?php

declare(strict_types = 1);

namespace Drupal\joinup_taxonomy;

use Drupal\Component\Serialization\Json;
use EasyRdf\Graph;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;

/**
 * Reusable methods to alter vocabulary graphs.
 */
trait VocabularyGraphAlterersTrait {

  /**
   * Prepares the graph.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function prepareGraph(string $vid, Graph $graph): void {
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
   * Alters the 'language' vocabulary graph.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForLanguage(string $vid, Graph $graph): void {
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
   * Alters the 'spatial' vocabulary graph.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForSpatial(string $vid, Graph $graph): void {
    // Remove deprecated countries from the country list. See ISAICP-3442.
    foreach ($graph->resources() as $resource) {
      if ($graph->hasProperty($resource, 'http://publications.europa.eu/ontology/authority/end.use')) {
        $graph->deleteEntireResource($resource);
      }
    }
  }

  /**
   * Alters the 'file_type' vocabulary graph.
   *
   * Extract extensions and mime-type from referred BNodes and fill two literals
   * that can be mapped. Due to the extensions and mime-type RDF representation,
   * this cannot be done by a simple RDF field mapping.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForFileType(string $vid, Graph $graph): void {
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
   * Alters the 'owner_type' vocabulary graph.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForOwnerType(string $vid, Graph $graph): void {
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3084
    $graph->addResource(
      'http://purl.org/adms/licencetype/ViralEffect-ShareAlike',
      'http://www.w3.org/2004/02/skos/core#inScheme',
      'http://purl.org/adms/licencetype/1.1',
    );
  }

  /**
   * Alters the 'eira' vocabulary graph by adding the extra terms.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForEiraAddExtraProperties(string $vid, Graph $graph): void {
    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

    foreach ($graph->resources() as $resource) {
      if (!str_ends_with($resource->getUri(), '_Properties')) {
        continue;
      }

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
          $value = $object instanceof Resource ? $object->getUri() : $object->getValue();
          if ($value) {
            $rows[] = [
              'property' => $shortenedProperty,
              'value' => $value,
            ];
          }
        }
      }

      // Inject properties in the main term.
      $graph->addLiteral($mainResource, 'http://joinup.eu/eira/properties', Json::encode([
        'namespaces' => $namespaces,
        'rows' => $rows,
      ]));

      // We've extracted what we need. Drop this resource and jump to next.
      $graph->deleteEntireResource($resource);
    }
  }

  /**
   * Alters the 'eira' vocabulary graph.
   *
   * @param string $vid
   *   The graph to be altered.
   * @param \EasyRdf\Graph $graph
   *   The vocabulary.
   */
  public static function alterGraphForEira(string $vid, Graph $graph): void {
    RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
    // Replace the `dc` namespace with `dct` - requested by the client.
    RdfNamespace::delete('dc');
    RdfNamespace::delete('dcterms');
    RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
    RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

    foreach ($graph->resources() as $resource) {
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
    }
  }

}
