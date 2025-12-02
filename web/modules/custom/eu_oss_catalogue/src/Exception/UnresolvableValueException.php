<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Exception;

use Drupal\Core\Serialization\Yaml;

/**
 * Exception used for logging field resolution failures.
 *
 * This exception is created and logged (not thrown) when a field value cannot
 * be resolved during catalogue import, such as when a taxonomy term doesn't
 * exist in a controlled vocabulary.
 *
 * @see \Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Resolver\TaxonomyTermResolver::resolve()
 */
class UnresolvableValueException extends EuOssCatalogueException {

  public function __construct(
    public readonly string $fieldName,
    public readonly string $path,
    public readonly mixed $value,
    public readonly array $data,
    int $code = 0,
    ?\Throwable $previous = NULL,
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
