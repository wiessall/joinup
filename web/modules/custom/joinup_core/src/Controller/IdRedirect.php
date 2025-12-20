<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a route callback to redirect /data/{uuid} to the RDF canonical page.
 */
class IdRedirect extends ControllerBase {

  public function __construct(
    #[Autowire(service: 'rdf_sync.mapper')]
    protected readonly RdfSyncMapper $mapper,
  ) {}

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
    if (!static::getEntityTypeFromPersistentUriNamespace($namespace)) {
      return $this->redirect('joinup_core.not_found');
    }

    // The UUID has been encoded early to allow slashes.
    // @see \Drupal\joinup_core\PathProcessor\IdRedirectUuidProcessor::processInbound()
    $uuid = urldecode($uuid);

    $id = "http://data.europa.eu/$namespace/$uuid";

    if (($entity = $this->mapper->getEntityByUri($id))) {
      assert($entity instanceof RdfSyncEntityInterface);
      $entity_type_id = $entity->getEntityTypeId();
      $route_parameters = [
        $entity_type_id => $entity->id(),
      ];

      if ($entity_type_id === 'assessment') {
        $route_parameters['assessment_type'] = $entity->bundle();
      }

      return $this->redirect("entity.$entity_type_id.canonical", $route_parameters, [], 301);
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
   *   $namespace is passed, returns the mapped entity type or NULL if none.
   *
   * @see http://data.europa.eu/URI.html
   */
  public static function getEntityTypeFromPersistentUriNamespace(?string $namespace = NULL): array|string|null {
    $namespaces = [
      'w21' => 'node',
      'dr8' => 'taxonomy_term',
      's2k' => 'assessment',
    ];

    if (!$namespace) {
      return $namespaces;
    }

    return $namespaces[$namespace] ?? NULL;
  }

}
