<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a route callback to redirect /data/{uuid} to the RDF canonical page.
 */
class IdRedirect extends ControllerBase {

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   */
  public function __construct(protected Connection $database) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static($container->get('database'));
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
    // @see \Drupal\joinup_core\PathProcessor\IdRedirectNamespaceProcessor::processInbound()
    $uuid = urldecode($uuid);

    $id = "http://data.europa.eu/$namespace/$uuid";
    if ($entity_type_id === 'taxonomy_term') {
      $id = $this->database->select('taxonomy_term__uri', 'u')
        ->fields('u', ['entity_id'])
        ->condition('u.uri_value', $id)
        ->execute()
        ->fetchField();
      if (!$id) {
        return $this->redirect('joinup_core.not_found');
      }
    }

    return $this->redirect("entity.$entity_type_id.canonical", [
      $entity_type_id => $id,
    ], [], 301);
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
  public static function getEntityTypeFromPersistentUriNamespace(?string $namespace = NULL) {
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
