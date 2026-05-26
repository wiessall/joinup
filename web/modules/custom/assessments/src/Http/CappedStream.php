<?php

declare(strict_types=1);

namespace Drupal\assessments\Http;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that throws once cumulative writes exceed a byte cap.
 *
 * Used as a Guzzle SINK to enforce a hard byte ceiling during transfer, so a
 * remote that lies about Content-Length or omits it cannot trick the
 * application into buffering or persisting more than allowed.
 */
final class CappedStream implements StreamInterface {

  use StreamDecoratorTrait;

  /**
   * The wrapped stream. Required by StreamDecoratorTrait.
   */
  private StreamInterface $stream;

  /**
   * Cumulative number of bytes written through this decorator.
   */
  private int $written = 0;

  public function __construct(
    StreamInterface $stream,
    private readonly int $max,
  ) {
    $this->stream = $stream;
  }

  /**
   * {@inheritdoc}
   */
  public function write(string $string): int {
    $this->written += strlen($string);
    if ($this->written > $this->max) {
      throw new MaxDownloadSizeExceededException(sprintf(
        'Document exceeds the maximum allowed download size of %d bytes.',
        $this->max,
      ));
    }
    return $this->stream->write($string);
  }

}
