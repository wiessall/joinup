<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_rdf\Unit;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\joinup_rdf\Plugin\Validation\Constraint\IDMaxLengthConstraint;
use Drupal\joinup_rdf\Plugin\Validation\Constraint\IDMaxLengthConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Tests the IDMaxLength constraint.
 *
 * @coversDefaultClass \Drupal\joinup_rdf\Plugin\Validation\Constraint\IDMaxLengthConstraintValidator
 */
class IDMaxLengthConstraintValidatorTest extends UnitTestCase {

  /**
   * @covers ::validate
   *
   * @dataProvider providerValidate
   */
  public function testValidate(EntityInterface $entity, bool $valid, int $max): void {
    $context = $this->createMock(ExecutionContextInterface::class);

    if ($valid) {
      $context->expects($this->never())
        ->method('addViolation');
    }
    else {
      $context->expects($this->once())
        ->method('addViolation');
    }

    $constraint = new IDMaxLengthConstraint();
    $constraint->max = $max;

    $validator = new IDMaxLengthConstraintValidator();
    $validator->initialize($context);
    $validator->validate($entity, $constraint);
  }

  /**
   * Data provider for ::testValidate.
   */
  public function providerValidate(): array {
    $data = [];

    $entity = $this->getMockBuilder(EntityInterface::class)
      ->getMock();
    $entity->expects($this->any())
      ->method('id')
      ->willReturn('https://joinup.ec.europa.eu');

    $data[] = [$entity, TRUE, 100];
    $data[] = [clone $entity, FALSE, 10];

    return $data;
  }

}
