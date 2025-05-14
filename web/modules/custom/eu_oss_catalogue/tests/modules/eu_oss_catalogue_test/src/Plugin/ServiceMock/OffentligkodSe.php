<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\Attribute\ServiceMock;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Service mock of the Swedish catalogue.
 *
 * @see \Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\OffentligkodSe
 */
#[ServiceMock(
  id: "offentligkod_se",
  label: new TranslatableMarkup("OffentligkodSe"),
  weight: 0,
)]
class OffentligkodSe extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return !$this->isIntegrationTest() &&
      ((string) $request->getUri() === 'https://offentligkod.se/api/');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $fixtureFilePath = dirname(__DIR__, 5) . '/fixtures/http_response/offentligkod_se.yml';
    assert(file_exists($fixtureFilePath));
    $response = Json::encode(Yaml::decode(file_get_contents($fixtureFilePath)));
    assert(is_string($response));
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
