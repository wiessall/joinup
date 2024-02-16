<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup\Unit;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\joinup\Form\ExportUserListForm;
use Drupal\joinup_user\EntityAuthorshipHelperInterface;

/**
 * Tests certain atomic methods of ExportUserListForm.
 *
 * @group joinup
 *
 * @coversDefaultClass \Drupal\joinup\Form\ExportUserListForm
 */
class ExportUserListFormTest extends UnitTestCase {

  /**
   * The export form instance.
   */
  protected ExportUserListForm $formInstance;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();

    $this->formInstance = new ExportUserListForm(
      $this->prophesize(EntityTypeManagerInterface::class)->reveal(),
      $this->prophesize(EntityAuthorshipHelperInterface::class)->reveal(),
      $this->prophesize(FileSystemInterface::class)->reveal(),
      $this->prophesize(CsrfTokenGenerator::class)->reveal()
    );
  }

  /**
   * @covers ::getFirstValue
   *
   * @dataProvider providerTestGetFirstValue
   */
  public function testGetFirstValue(array $transformedValue, string $expectedValue): void {
    $methodReflection = (new \ReflectionClass($this->formInstance))
      ->getMethod('getFirstValue');

    $this->assertEquals(
      $expectedValue,
      $methodReflection->invoke($this->formInstance, $transformedValue)
    );
  }

  /**
   * Data provider of ::testGetFirstValue.
   *
   * @return array[]
   *   The test cases.
   */
  public function providerTestGetFirstValue(): array {
    return [
      'Empty array' => [
        'Transformed' => [],
        'Expected value' => '',
      ],
      'Non-empty array' => [
        'Transformed' => [['value' => 1], ['value' => 2]],
        'Expected value' => '1',
      ],
      'Value item without "value" property' => [
        'Transformed' => [['target_id' => 1]],
        'Expected value' => '1',
      ],
    ];
  }

}
