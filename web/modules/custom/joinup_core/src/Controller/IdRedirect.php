<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a route callback to redirect /data/{uuid} to the RDF canonical page.
 */
class IdRedirect extends ControllerBase {

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function __construct(
    protected RdfSyncMapper $mapper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * Redirects to the RDF entity canonical page.
   *
   * @param string $namespace
   *   The persistent URI namespace. See http://data.europa.eu/URI.html.
   * @param string $uuid
   *   The unique part of the RDF entity ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   */
  public function redirectToRdfEntity(string $namespace, string $uuid): RedirectResponse {
    if (!$entity_type_id = static::getEntityTypeFromPersistentUriNamespace($namespace)) {
      return $this->redirect('joinup_core.not_found');
    }

    // The UUID has been encoded early to allow slashes.
    // @see \Drupal\joinup_core\PathProcessor\IdRedirectUuidProcessor::processInbound()
    $uuid = urldecode($uuid);

    $id = "http://data.europa.eu/$namespace/$uuid";

    // @todo This a temporary solution during conversion of RDF entities to
    //   nodes. Convert to a stable solution in ISAICP-8376.
    if (
      $entity_type_id === 'rdf_entity' &&
      ($entity = $this->entityTypeManager()->getStorage('rdf_entity')->load($id)) &&
      $entity instanceof GroupInterface
    ) {
      return $this->redirect("entity.rdf_entity.canonical", [
        $entity_type_id => $id,
      ], [], 301);
    }
    // @todo Remove the second condition, for solution stub exception, in ISAICP-8572.
    elseif (($entity = $this->mapper->getEntityByUri($id)) && !$entity instanceof SolutionInterface) {
      assert($entity instanceof RdfSyncEntityInterface);
      $entity_type_id = $entity->getEntityTypeId();
      return $this->redirect("entity.$entity_type_id.canonical", [
        $entity_type_id => $entity->id(),
      ], [], 301);
    }

    return $this->redirect('joinup_core.not_found');
  }

  /**
   * Returns the entity type given an EU persistent URI namespace.
   *
   * @param string|null $namespace
   *   (optional) The EU persistent URI namespace.
   *
   * @return string[]|string|null
   *   If $namespace is missed, it returns the whole mapping array. If
   *   $namespace is passe, returns the mapped entity type or NULL if none.
   *
   * @see http://data.europa.eu/URI.html
   */
  public static function getEntityTypeFromPersistentUriNamespace(?string $namespace = NULL): array|string|null {
    $namespaces = [
      'w21' => 'rdf_entity',
      'dr8' => 'taxonomy_term',
    ];

    if (!$namespace) {
      return $namespaces;
    }

    return $namespaces[$namespace] ?? NULL;
  }

}
