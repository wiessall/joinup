<?php

declare(strict_types=1);

namespace Drupal\joinup_seo;

use Drupal\Core\Url;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Helper service to ensure that data are exported properly.
 */
readonly class JoinupSeoExportHelper implements JoinupSeoExportHelperInterface {

  /**
   * Serialization format.
   *
   * @const string
   */
  protected const SERIALIZATION_FORMAT = 'jsonld';

  public function __construct(
    #[Autowire(service: 'serializer')]
    protected SerializerInterface $serializer,
    #[Autowire(param: 'app.root')]
    protected string $appRoot,
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
    $context_path = $this->appRoot . '/assets/adms-ap-2.01.jsonld';
    $frame = json_decode(file_get_contents($context_path));
    $serialized = $this->serializer->serialize($entity, self::SERIALIZATION_FORMAT, ['frame' => $frame]);

    if (!is_array($decodedSerialized = json_decode($serialized, TRUE))) {
      return '';
    }

    return json_encode(
      [
        '@context' => Url::fromUri('base:/assets/adms-ap-2.01.jsonld', ['absolute' => TRUE])->toString(),
        '@graph' => $decodedSerialized,
      ],
      JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
    );
  }

}
