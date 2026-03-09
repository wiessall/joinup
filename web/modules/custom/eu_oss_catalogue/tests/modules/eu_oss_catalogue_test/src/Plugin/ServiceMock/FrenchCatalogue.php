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
 * Intercepts any HTTP requests made to French catalogue API.
 */
#[ServiceMock(
  id: "awesome_code_gouv_fr",
  label: new TranslatableMarkup("French catalogue API"),
  weight: 0,
)]
class FrenchCatalogue extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return !$this->isIntegrationTest() && ($request->getUri()
      ->getHost() === 'code.gouv.fr');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $response = Json::encode(Yaml::decode(file_get_contents(__DIR__ . '/../../../../../fixtures/http_response/french_catalogue.yml')));
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
