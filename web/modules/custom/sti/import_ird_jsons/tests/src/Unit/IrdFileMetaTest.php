<?php

declare(strict_types=1);

namespace Drupal\Tests\import_ird_jsons\Unit;

use Drupal\import_ird_jsons\IrdFileMeta;
use Drupal\Tests\UnitTestCase;

/**
 * meta.field_config decides how every claim of a file is stored.
 *
 * A wrong answer is silent: categorical values would be stored as plain text
 * (no terms, no facets). Hence the tests.
 *
 * @coversDefaultClass \Drupal\import_ird_jsons\IrdFileMeta
 * @group import_ird_jsons
 */
final class IrdFileMetaTest extends UnitTestCase {

  public function testReadsFieldConfig(): void {
    $meta = IrdFileMeta::fromDocument(['meta' => [
      'collection_id' => 'test002',
      'field_config' => [
        'Country' => ['categorical' => TRUE],
        'Domain' => ['categorical' => TRUE, 'separator' => '|'],
        'Latitude' => ['categorical' => FALSE],
      ],
    ]]);
    $this->assertSame('test002', $meta->collectionId);
    $this->assertTrue($meta->isCategorical('Country'));
    $this->assertFalse($meta->isCategorical('Latitude'));
    $this->assertFalse($meta->isCategorical('Unlisted'), 'Keys without an entry stay literal.');
    $this->assertSame('|', $meta->separator('Domain'));
    $this->assertNull($meta->separator('Country'));
  }

  /**
   * @dataProvider invalidDocuments
   */
  public function testInvalidMetaThrows(mixed $document): void {
    $this->expectException(\InvalidArgumentException::class);
    IrdFileMeta::fromDocument($document);
  }

  public static function invalidDocuments(): array {
    return [
      'not an object' => [NULL],
      'no meta' => [['records' => []]],
      'no collection' => [['meta' => ['field_config' => []]]],
      'no field_config' => [['meta' => ['collection_id' => 'x']]],
      'categorical not boolean' => [['meta' => ['collection_id' => 'x', 'field_config' => ['A' => ['categorical' => 'yes']]]]],
    ];
  }

}
