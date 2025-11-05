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
    $openapi_file = __DIR__ . '/../../openapi/assessments.openapi.yaml';

    if (!file_exists($openapi_file)) {
      return [
        '#markup' => $this->t('OpenAPI specification file not found.'),
      ];
    }

    $schema = Yaml::decode(file_get_contents($openapi_file));

    return [
      'api_docs' => [
        '#type' => 'openapi_ui',
        '#openapi_ui_plugin' => 'swagger',
        '#openapi_schema' => $schema,
      ],
    ];
  }

}
