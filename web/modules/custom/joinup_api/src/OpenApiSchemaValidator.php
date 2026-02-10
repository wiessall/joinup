<?php

declare(strict_types=1);

namespace Drupal\joinup_api;

use JsonSchema\Validator;
use cebe\openapi\Reader;

/**
 * Validates a scheme against an OpenAPI specification.
 */
class OpenApiSchemaValidator {

  /**
   * Validates a structured array against a schema defined in an OpenAPI YAML.
   *
   * @param array $data
   *   Data to be validated.
   * @param string $openApiFile
   *   A path the OpenAPI file.
   * @param string $schema
   *   The schema to check against.
   *
   * @return list<array{property: string, pointer: string, message: string, constraint: array{name: string, params: array}, context: int}>
   *   List of errors.
   *
   * @throws \cebe\openapi\exceptions\IOException
   *   When the $openApiFile is not a valid, reachable and readable file.
   * @throws \InvalidArgumentException
   *   If any of the following happens:
   *   - The $openApiFile file is not following the OpenAPI swagger schema.
   *   - The $schema schema doesn't exist in the $openApiFile file.
   */
  public function validate(array $data, string $openApiFile, string $schema): array {
    $openapi = Reader::readFromYamlFile($openApiFile);

    if (!$openapi->validate()) {
      throw new \InvalidArgumentException("The file $openApiFile has invalid OpenAPI schema. Reported errors:\n" . print_r($openapi->getErrors(), TRUE));
    }

    $schemaDef = $openapi->components->schemas[$schema]->getSerializableData();

    // Convert associative arrays to objects.
    $data = json_decode(json_encode($data));

    $validator = new Validator();
    $validator->validate($data, $schemaDef);

    return $this->prepareErrors($validator->getErrors());
  }

  /**
   * Enhances the error messages.
   *
   * @param list<array> $errors
   *   List of errors grouped by property path, passed by reference.
   *
   * @return array<string, list<string>>
   *   Associative array where the keys are erroneous element paths and the
   *   values are the error messages associated with them.
   */
  protected function prepareErrors(array $errors): array {
    $preparedErrors = [];

    foreach ($errors as $error) {
      $path = $error['pointer'] ?: '/';
      $preparedErrors[$path][] = $error['message'];
    }
    ksort($preparedErrors);

    return $preparedErrors;
  }

}
