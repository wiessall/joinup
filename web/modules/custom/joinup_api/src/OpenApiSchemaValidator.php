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
   *   The schema to checked against.
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

    $errors = [];
    foreach ($validator->getErrors() as $error) {
      $path = $error['pointer'] ?: '/';
      $errors[$path][] = $error['message'];
    }

    $this->enhance($errors);

    return $errors;
  }

  /**
   * Enhances the error messages.
   *
   * @param array<string, list<string>> $errors
   *   List of errors grouped by property path, passed by reference.
   */
  protected function enhance(array &$errors): void {
    ksort($errors);

    // In Joinup we often assign two alternative schemas to a property by using
    // the oneOf operator. The practical case is when we need EITHER to create
    // an object, OR refer it by its ID. When we pass an object which doesn't
    // validate, the following sequence or error messages is generated:
    // - Object value found, but a string is required
    // - Failed to match exactly one schema
    // The same happens when the string is found but is invalid. But this looks
    // quite misleading for the consumer. We enhance by replacing both messages
    // with one more meaningful.
    array_walk($errors, function (array &$error): void {
      $oneOfMessageDelta = array_search(self::ONE_OF_MESSAGE, $error, TRUE);
      if ($oneOfMessageDelta === FALSE) {
        return;
      }

      $objectFoundStringRequiredMessageDelta = array_search(self::OBJECT_FOUND_STRING_REQUIRED_MESSAGE, $error, TRUE);
      unset($error[$oneOfMessageDelta]);
      if ($objectFoundStringRequiredMessageDelta !== FALSE) {
        unset($error[$objectFoundStringRequiredMessageDelta]);
        $error[] = self::OBJECT_FOUND_STRING_REQUIRED_MESSAGE_ALT;
      }

      $stringFoundObjectRequiredMessageDelta = array_search(self::STRING_FOUND_OBJECT_REQUIRED_MESSAGE, $error, TRUE);
      unset($error[$oneOfMessageDelta]);
      if ($stringFoundObjectRequiredMessageDelta !== FALSE) {
        unset($error[$stringFoundObjectRequiredMessageDelta]);
        $error[] = self::STRING_FOUND_OBJECT_REQUIRED_MESSAGE_ALT;
      }

      $error = array_values($error);
    });
  }

  /**
   * Message created by when none of oneOf schemas is validated.
   */
  private const string ONE_OF_MESSAGE = 'Failed to match exactly one schema';

  /**
   * Message when the property defined with oneOf contains an invalid object.
   */
  private const string OBJECT_FOUND_STRING_REQUIRED_MESSAGE = 'Object value found, but a string is required';

  /**
   * Replacement message when object is invalid.
   */
  private const string OBJECT_FOUND_STRING_REQUIRED_MESSAGE_ALT = 'An object or a string is required. Object value found, but is invalid';

  /**
   * Message when the property defined with oneOf contains an invalid string.
   */
  private const string STRING_FOUND_OBJECT_REQUIRED_MESSAGE = 'String value found, but an object is required';

  /**
   * Replacement message when string is invalid.
   */
  private const string STRING_FOUND_OBJECT_REQUIRED_MESSAGE_ALT = 'An object or a string is required. String value found, but is invalid';

}
