<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\sti_rdf\Service\MappingTermSource;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves term lists to the SKOS mapper, same origin as the mapper page.
 */
final class MappingTermsController implements ContainerInjectionInterface {

  public function __construct(
    private readonly MappingTermSource $termSource,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('sti_rdf.mapping_term_source'));
  }

  /**
   * Lists the sources: ATIO and every collection with claim properties.
   */
  public function sources(): JsonResponse {
    return new JsonResponse($this->termSource->sources());
  }

  /**
   * Returns one source's terms with their graph URIs.
   */
  public function terms(string $source): JsonResponse {
    try {
      return new JsonResponse($this->termSource->terms($source));
    }
    catch (\InvalidArgumentException $e) {
      throw new NotFoundHttpException($e->getMessage(), $e);
    }
  }

}
