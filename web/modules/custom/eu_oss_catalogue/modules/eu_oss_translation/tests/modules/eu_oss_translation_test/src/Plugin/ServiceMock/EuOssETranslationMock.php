<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\tmgmt_ec_etranslation_test\Plugin\ServiceMock\ETranslationMock;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made to eTranslation API.
 *
 * @ServiceMock(
 *   id = "eu_oss_etranslation",
 *   label = @Translation("EU OSS eTranslation API"),
 *   weight = -1,
 * )
 */
class EuOssETranslationMock extends ETranslationMock {

  /**
   * Provides response for the get-domains endpoint.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Response with domains.
   */
  protected function getDomains(): ResponseInterface {
    $data = Json::encode([
      'SPD' => [
        'name' => 'EU Formal Language',
        'languagePairs' => [
          'DE-EN',
          'IT-EN',
        ],
      ],
    ]);

    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($data),
    ], $data);
  }

}
