<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\Attribute\ServiceMock;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made to Dutch catalogue API.
 */
#[ServiceMock(
  id: "developer_overheid",
  label: new TranslatableMarkup("Developer overheid API"),
  weight: 0,
)]
class DeveloperOverheid extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return !$this->isIntegrationTest() && ($request->getUri()
      ->getHost() === 'gist.githubusercontent.com');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $response = Json::encode(Yaml::decode(file_get_contents(__DIR__ . '/../../../../../fixtures/http_response/developer_overheid.yml')));
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
