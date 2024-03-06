<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Exception;

use Drupal\Core\Serialization\Yaml;

/**
 * Exception to be thrown when a value cannot be resolved.
 */
class UnresolvableValueException extends EuOssCatalogueException {

  /**
   * Constructs a new exception instance.
   *
   * @param string $fieldName
   *   The field name.
   * @param string $path
   *   The nested array path to the value.
   * @param mixed $value
   *   The value.
   * @param array $data
   *   Fetched data.
   * @param int $code
   *   (optional) The Exception code.
   * @param \Throwable|null $previous
   *   (optional) The previous throwable used for the exception chaining.
   */
  public function __construct(
    public readonly string $fieldName,
    public readonly string $path,
    public readonly mixed $value,
    public readonly array $data,
    int $code = 0,
    ?\Throwable $previous = NULL
  ) {
    $printedValue = trim(var_export($this->value, TRUE));
    $printedData = Yaml::encode($this->data);

    $message = <<<MESSAGE
      <p>Cannot resolve field: <code>$this->fieldName</code></p>
      <p>Path: <code>$this->path</code></p>
      <p>Value:</p>
      <pre>
      $printedValue
      </pre>
      <p>Full data:</p>
      <pre>
      $printedData
      </pre>
      MESSAGE;

    parent::__construct($message, $code, $previous);
  }

}
