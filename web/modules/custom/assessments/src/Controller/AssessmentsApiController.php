<?php

declare(strict_types=1);

namespace Drupal\assessments\Controller;

use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Controller\ControllerBase;

/**
 * Controller for Assessments API documentation.
 */
class AssessmentsApiController extends ControllerBase {

  /**
   * Displays the Assessments API documentation using OpenAPI UI.
   *
   * @return array
   *   A render array for the OpenAPI UI.
   */
  public function apiDocs(): array {
    $openapiFile = __DIR__ . '/../../openapi/assessments.openapi.yaml';

    if (!file_exists($openapiFile)) {
      return [
        '#markup' => $this->t('OpenAPI specification file not found.'),
      ];
    }

    $schema = Yaml::decode(file_get_contents($openapiFile));

    if (isset($schema['paths']['/token']['$ref'])) {
      // For some reason, the relative path to oauth2.openapi.yaml is messed up
      // when rendering via controller. Override with a full URL.
      $schema['paths']['/token']['$ref'] = $GLOBALS['base_url'] . '/modules/custom/joinup_api/openapi/oauth2.openapi.yaml#/paths/~1token';
    }

    return [
      'api_docs' => [
        '#type' => 'openapi_ui',
        '#openapi_ui_plugin' => 'swagger',
        '#openapi_schema' => $schema,
      ],
    ];
  }

}
