<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP request made to unknown directions and returns 501.
 *
 * @ServiceMock(
 *   id = "not_allowed",
 *   label = @Translation("Request catcher"),
 *   weight = 100,
 * )
 */
class NotAllowedMock extends PluginBase implements ServiceMockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    return new Response(501);
  }

}
