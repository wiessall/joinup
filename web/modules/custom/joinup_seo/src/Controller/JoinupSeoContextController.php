<?php

declare(strict_types=1);

namespace Drupal\joinup_seo\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Extension\ExtensionPathResolver;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the ADMS-AP JSON-LD context document under a dedicated URL.
 */
class JoinupSeoContextController extends ControllerBase {

  public function __construct(
    protected ExtensionPathResolver $extensionPathResolver,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('extension.path.resolver'),
    );
  }

  /**
   * Returns the ADMS-AP JSON-LD context document.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The context document, served as "application/ld+json".
   */
  public function admsApContext(): BinaryFileResponse {
    $path = $this->extensionPathResolver->getPath('module', 'joinup_seo') . '/assets/adms-ap-2.01.jsonld';
    $response = new BinaryFileResponse($path);
    $response->headers->set('Content-Type', 'application/ld+json');
    $response->setMaxAge(86400);
    $response->setPublic();

    return $response;
  }

}
