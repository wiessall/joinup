<?php

declare(strict_types=1);

namespace Drupal\joinup_seo;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\sparql_entity_storage\SparqlSerializerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Helper service to ensure that data are exported properly.
 */
class JoinupSeoExportHelper implements JoinupSeoExportHelperInterface {

  /**
   * Serialization format.
   *
   * @const string
   */
  protected const SERIALIZATION_FORMAT = 'jsonld';

  /**
   * Constructs a JoinupSeoExportHelper object.
   *
   * @param \Drupal\sparql_entity_storage\SparqlSerializerInterface $sparQlSerializer
   *   The SPARQL serializer service.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The general serializer service.
   */
  public function __construct(
    protected SparqlSerializerInterface $sparQlSerializer,
    protected SerializerInterface $serializer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function exportRdfEntityMetadata(RdfInterface $entity): string {
    $output = $this->sparQlSerializer->serializeEntity($entity, self::SERIALIZATION_FORMAT, self::getOptions());
    // The serializer returns also data where the entity is referred, thus, not
    // related to the metadata. Keep only the entry that has a "@type" property.
    $output = json_decode($output);
    foreach ($output->{"@graph"} as $object) {
      if (isset($object->{"@type"})) {
        $output->{"@graph"} = [$object];
        return json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
      }
    }
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityJsonLdString(RdfSyncEntityInterface|RdfInterface $entity): string {
    // @todo Remove this BC layer in ISAICP-8376.
    if ($entity instanceof RdfInterface) {
      return $this->exportRdfEntityMetadata($entity);
    }

    assert($entity instanceof RdfSyncEntityInterface);
    return $this->exportEntityJsonLd($entity);
  }

  /**
   * Exports the given RDF-synchronized entity as JSON-LD string.
   *
   * @param \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface $entity
   *   The RDF-synchronized entity to export.
   *
   * @return string
   *   The exported string.
   */
  protected function exportEntityJsonLd(RdfSyncEntityInterface $entity): string {
    $serialized = $this->serializer->serialize($entity, self::SERIALIZATION_FORMAT, $options = self::getOptions());
    if (
      !is_array($decodedSerialized = json_decode($serialized, TRUE))
    ) {
      return '';
    }

    return json_encode(
      [
        '@context' => $options['frame']->{'@context'},
        '@graph' => $decodedSerialized,
      ],
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
  }

  /**
   * Returns a static list of options for the serializer.
   *
   * @return array
   *   An array of options for the JSON-LD serializer.
   */
  protected function getOptions(): array {
    // The \EasyRdf\Serialiser\JsonLd uses the \ML\JsonLD class to generate the
    // final output. The \ML\JsonLD has two ways of using the context. Either
    // downloading it from a web source or by passing an object. For the web
    // resource, the expected response has to be in the 'application/jsonld'
    // format, something that we do not have. However, even for performance and
    // availability purposes, a local file is used instead.
    $context_path = __DIR__ . '/../assets/adms-ap-2.01.jsonld';
    $content = file_get_contents($context_path);
    $content = json_decode($content);
    return [
      // Frame contains the "@context" and passes this to the values.
      'frame' => $content,
    ];
  }

}
