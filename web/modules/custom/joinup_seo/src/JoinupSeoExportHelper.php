<?php

declare(strict_types=1);

namespace Drupal\joinup_seo;

use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
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
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The general serializer service.
   */
  public function __construct(
    protected SerializerInterface $serializer,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getEntityJsonLdString(RdfSyncEntityInterface $entity): string {
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
