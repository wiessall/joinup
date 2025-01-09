<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test_not_allowed_mock\Plugin\ServiceMock;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Intercepts any HTTP request made to unknown directions and returns 501.
 *
 * @ServiceMock(
 *   id = "not_allowed",
 *   label = @Translation("Request catcher"),
 *   weight = 100,
 * )
 */
class NotAllowedMock extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected RequestStack $requestStack,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    // Only applies if request is external.
    return $this->requestStack->getCurrentRequest()->getSchemeAndHttpHost() !== "{$request->getUri()->getScheme()}://{$request->getUri()->getHost()}";
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    return new Response(501);
  }

}
