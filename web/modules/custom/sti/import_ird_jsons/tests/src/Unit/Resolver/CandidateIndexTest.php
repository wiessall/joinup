<?php

declare(strict_types=1);

namespace Drupal\Tests\import_ird_jsons\Unit\Resolver;

use Drupal\Tests\UnitTestCase;
use Drupal\import_ird_jsons\Resolver\CandidateIndex;
use Drupal\import_ird_jsons\Resolver\MatchResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * A wrong match links content to the wrong organisation and nothing errors. These cases come from a reviewed
 * deduplication of 1,161 organisations (ATIO owners, November 2026).
 */
#[CoversClass(CandidateIndex::class)]
#[Group('import_ird_jsons')]
final class CandidateIndexTest extends UnitTestCase {

  /**
   *
   */
  private function index(array $entities): CandidateIndex {
    $index = new CandidateIndex();
    foreach ($entities as $id => $names) {
      $index->add((string) $id, (array) $names[0], (array) ($names[1] ?? []));
    }
    return $index;
  }

  /**
   * Case, accents, legal suffix, "The" and an embedded acronym do not make another organisation.
   */
  public function testVariantsResolveExactly(): void {
    $index = $this->index([
      1 => ['Sokoine University of Agriculture', 'SUA'],
      2 => ['Red Diamond Compost Inc.'],
      3 => ['Institut National de la Recherche Agronomique d’Algérie'],
    ]);
    $this->assertSame('1', $index->resolve('SOKOINE UNIVERSITY OF AGRICULTURE (SUA)')->id);
    $this->assertSame('2', $index->resolve('Red Diamond Compost')->id);
    $this->assertSame('3', $index->resolve("Institut national de la recherche agronomique d'Algerie")->id);
    $this->assertSame('1', $index->resolve('SUA')->id);
  }

  /**
   * A qualifier picks between same-named bodies and never crosses countries.
   */
  public function testQualifiersDecide(): void {
    $index = $this->index([
      1 => ['Ministry of Agriculture (Sri Lanka)'],
      2 => ['Ministry of Agriculture (Bangladesh)'],
    ]);
    $this->assertSame('2', $index->resolve('Ministry of Agriculture [Bangladesh]')->id);
    $this->assertNull($index->resolve('Ministry of Agriculture (Kenya)')->id);
    $this->assertNull($index->resolve('Ministry of Agriculture')->id);
  }

  /**
   * A generic name without a qualifier is not accepted, even when only one candidate exists.
   */
  public function testGenericNameNeedsQualifier(): void {
    $index = $this->index([1 => ['Forest Department (India)']]);
    $result = $index->resolve('Forest Department');
    $this->assertNull($result->id);
    $this->assertSame(MatchResult::AMBIGUOUS, $result->tier);
  }

  /**
   * Fuzzy matching accepts typos but not different words that look alike.
   */
  public function testFuzzyNeedsEveryWord(): void {
    $index = $this->index([
      1 => ['International Center for Biosaline Agriculture', 'ICBA'],
      2 => ['International Potato Center', 'CIP'],
    ]);
    $this->assertNull($index->resolve('Centre for Agriculture and Bioscience International')->id);
    $this->assertSame('2', $index->resolve('Intenational Potato Centre')->id);
  }

  /**
   * An acronym alone is enough only when the name next to it agrees.
   */
  public function testAcronymMustAgreeWithName(): void {
    $index = $this->index([1 => ['Caribbean Agricultural Research and Development Institute', 'CARDI']]);
    $this->assertNull($index->resolve('Cambodian Agricultural Research and Development Institute (CARDI)')->id);
    $this->assertSame('1', $index->resolve('CARDI')->id);
  }

  /**
   * Two entities with the same name, or a near tie, are never guessed.
   */
  public function testTiesAreAmbiguous(): void {
    $index = $this->index([1 => ['Fonterra'], 2 => ['Fonterra Pvt. Ltd.']]);
    $this->assertNull($index->resolve('Fonterra')->id);
    $this->assertSame(MatchResult::AMBIGUOUS, $index->resolve('Fonterra')->tier);
  }

  /**
   * Countries: codes only as typed in capitals, inverted official names, and neighbours never swapped.
   */
  public function testCountries(): void {
    $index = new CandidateIndex();
    foreach ([
      'KEN' => ['Kenya', 'KE'],
      'IRN' => ['Iran (Islamic Republic of)', 'IR'],
      'NER' => ['Niger', 'NE'],
      'NGA' => ['Nigeria', 'NG'],
      'SDN' => ['Sudan', 'SD'],
      'SSD' => ['South Sudan', 'SS'],
      'ISL' => ['Iceland', 'IS'],
      'IRL' => ['Ireland', 'IE'],
      'IND' => ['India', 'IN'],
      'COD' => ['Democratic Republic of the Congo', 'CD'],
      'COG' => ['Congo', 'CG'],
    ] as $iso3 => [$name, $iso2]) {
      $index->add($iso3, [$name], [], [$iso3, $iso2]);
    }
    $this->assertSame('KEN', $index->resolve('KE')->id);
    $this->assertSame('KEN', $index->resolve('KEN')->id);
    $this->assertNull($index->resolve('in')->id, 'a lower-case word is not a code');
    $this->assertSame('IRN', $index->resolve('Iran, Islamic Republic of')->id);
    $this->assertSame('NER', $index->resolve('Niger')->id);
    $this->assertSame('SDN', $index->resolve('Sudan')->id);
    $this->assertSame('COG', $index->resolve('Congo')->id);
    $this->assertSame('COD', $index->resolve('Congo, Democratic Republic of the')->id);
    $this->assertNull($index->resolve('Irland')->id, 'between Iceland and Ireland, a typo is not guessed');
  }

  /**
   * A name that matches nothing stays unmatched: technology names were imported as owners before.
   */
  public function testUnrelatedNameIsNone(): void {
    $index = $this->index([1 => ['International Rice Research Institute', 'IRRI']]);
    $this->assertSame(MatchResult::NONE, $index->resolve('Biochar')->tier);
  }

}
