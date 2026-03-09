<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidatorInterface;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\Attribute\ServiceMock;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made to the PublicCode validator API.
 */
#[ServiceMock(
  id: "publiccode_validator",
  label: new TranslatableMarkup("PublicCode Validator API"),
  weight: 0,
)]
class PublicCode extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * Marker embedded in YAML content to trigger an invalid API response.
   */
  const string INVALID_YAML_MARKER = '__eu_oss_publiccode_invalid__';

  /**
   * Marker embedded in YAML content to trigger a validator API exception.
   */
  const string EXCEPTION_YAML_MARKER = '__eu_oss_publiccode_exception__';

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return !$this->isIntegrationTest()
      && $request->getMethod() === PublicCodeValidatorInterface::VALIDATE_METHOD
      && str_ends_with($request->getUri()->getPath(), PublicCodeValidatorInterface::VALIDATE_PATH);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $body = (string) $request->getBody();

    if (str_contains($body, self::EXCEPTION_YAML_MARKER)) {
      return new Response(500, [], 'Internal Server Error');
    }

    $fixtureKey = str_contains($body, self::INVALID_YAML_MARKER) ? 'invalid' : 'valid';
    $response = Json::encode(
      Yaml::decode(
        file_get_contents(__DIR__ . '/../../../../../fixtures/http_response/publiccode_validator.yml')
      )[$fixtureKey]
    );

    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
