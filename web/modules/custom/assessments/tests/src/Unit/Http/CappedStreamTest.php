<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Unit\Http;

use Drupal\Tests\UnitTestCase;
use Drupal\assessments\Http\CappedStream;
use Drupal\assessments\Http\MaxDownloadSizeExceededException;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Psr\Http\Message\StreamInterface;

/**
 * Tests the streaming sink that enforces a hard byte cap.
 */
#[CoversClass(CappedStream::class)]
#[Group('assessments')]
class CappedStreamTest extends UnitTestCase {

  /**
   * Returns a fresh in-memory inner stream.
   */
  private function inner(): StreamInterface {
    return Utils::streamFor(fopen('php://memory', 'wb+'));
  }

  /**
   * Writes below the cap pass through to the inner stream.
   */
  public function testWritesUnderCapPassThrough(): void {
    $inner = $this->inner();
    $sink = new CappedStream($inner, 10);

    $sink->write('abc');
    $sink->write('de');

    $inner->rewind();
    $this->assertSame('abcde', $inner->getContents());
  }

  /**
   * Writing exactly the cap is allowed.
   */
  public function testWritesAtExactCapAllowed(): void {
    $inner = $this->inner();
    $sink = new CappedStream($inner, 5);

    $sink->write('abcde');

    $inner->rewind();
    $this->assertSame('abcde', $inner->getContents());
  }

  /**
   * A single oversized write throws.
   */
  public function testSingleOversizedWriteThrows(): void {
    $sink = new CappedStream($this->inner(), 5);

    $this->expectException(MaxDownloadSizeExceededException::class);
    $this->expectExceptionMessageMatches('/exceeds the maximum allowed download size of 5 bytes/');
    $sink->write('abcdef');
  }

  /**
   * Cumulative writes that cross the cap throw on the offending write.
   */
  public function testCumulativeWritesThrowAtCrossing(): void {
    $inner = $this->inner();
    $sink = new CappedStream($inner, 5);

    $sink->write('abc');

    $thrown = NULL;
    try {
      $sink->write('xyz');
    }
    catch (MaxDownloadSizeExceededException $e) {
      $thrown = $e;
    }
    $this->assertInstanceOf(MaxDownloadSizeExceededException::class, $thrown);

    // The first write completed before the second one threw.
    $inner->rewind();
    $this->assertSame('abc', $inner->getContents());
  }

}
