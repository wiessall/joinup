<?php

declare(strict_types=1);

namespace Drupal\adms_validator;

/**
 * A value object containing an individual schema violation.
 *
 * @todo Better document the meaning of the different properties.
 */
readonly class SchemaError {

  public function __construct(
    public ?string $className = NULL,
    public ?string $message = NULL,
    public ?string $object = NULL,
    public ?string $predicate = NULL,
    public ?string $ruleDescription = NULL,
    public ?string $ruleID = NULL,
    public ?string $ruleSeverity = NULL,
    public ?string $subject = NULL,
  ) {
  }

  /**
   * Factory method to create an instance from a record object.
   */
  public static function fromRecord(\stdClass $record): self {
    return new self(
      className: !empty($record->Class_Name) ? strval($record->Class_Name) : NULL,
      message: !empty($record->Message) ? strval($record->Message) : NULL,
      object: !empty($record->Object) ? strval($record->Object) : NULL,
      predicate: !empty($record->Predicate) ? strval($record->Predicate) : NULL,
      ruleDescription: !empty($record->Rule_Description) ? strval($record->Rule_Description) : NULL,
      ruleID: !empty($record->Rule_ID) ? strval($record->Rule_ID) : NULL,
      ruleSeverity: !empty($record->Rule_Severity) ? strval($record->Rule_Severity) : NULL,
      subject: !empty($record->Subject) ? strval($record->Subject) : NULL
    );
  }

}
