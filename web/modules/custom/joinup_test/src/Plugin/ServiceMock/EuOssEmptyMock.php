<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made by EU OSS requests.
 *
 * @ServiceMock(
 *   id = "eu_oss_empty",
 *   label = @Translation("EU OSS Empty"),
 *   weight = 10,
 * )
 */
class EuOssEmptyMock extends PluginBase implements ServiceMockPluginInterface {

  /**
   * Drupal States API key of the status of this service mock.
   *
   * @const string
   */
  public const OSS_CATALOGUE_MOCK_STATE_KEY = 'joinup_test.oss_catalogue.enabled';

  /**
   * The list of excluded hosts.
   *
   * @var string[]
   */
  const EXCLUDED_EXTRA_HOSTS = [
    'gzoom.mapsgroup.it',
    'api.developers.italia.it',
    'code.europa.eu',
  ];

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    if (!\Drupal::state()->get(self::OSS_CATALOGUE_MOCK_STATE_KEY, FALSE)) {
      return FALSE;
    }
    $host = $request->getUri()->getHost();
    // Apply to any github|gitlab|bitbucket hosts. We do not want our behat
    // tests to connect to these services automatically.
    if (preg_match('/github|gitlab|bitbucket/', $host)) {
      return TRUE;
    }

    return in_array($request->getUri()->getHost(), self::EXCLUDED_EXTRA_HOSTS, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $response = Json::encode([]);
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
