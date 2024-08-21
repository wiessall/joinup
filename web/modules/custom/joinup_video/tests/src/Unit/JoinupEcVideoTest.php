<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_video\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\joinup_video\Plugin\video_embed_field\Provider\JoinupEcVideo;

/**
 * Tests the Joinup EC Video plugin.
 *
 * @coversDefaultClass \Drupal\joinup_video\Plugin\video_embed_field\Provider\JoinupEcVideo
 *
 * @group joinup_video
 */
class JoinupEcVideoTest extends UnitTestCase {

  /**
   * Tests the extraction of ID from input.
   *
   * @param string $url
   *   The input URL.
   * @param string|false $expected_id
   *   The expected ID or FALSE if it cannot be extracted from $url.
   *
   * @covers ::getIdFromInput
   * @dataProvider providerTestGetIdFromInput
   */
  public function testGetIdFromInput($url, $expected_id): void {
    $actual_id = JoinupEcVideo::getIdFromInput($url);
    $this->assertEquals($expected_id, $actual_id);
  }

  /**
   * Provides test cases for ::testGetIdFromInput.
   */
  public function providerTestGetIdFromInput(): array {
    return [
      'Current URL' => [
        'https://audiovisual.ec.europa.eu/en/video/I-136289',
        'I-136289',
      ],
      'Current embed URL - video param' => [
        'https://audiovisual.ec.europa.eu/corporateplayer/index.html?video=I-136289',
        'I-136289',
      ],
      'Current embed URL - ref param' => [
        'https://audiovisual.ec.europa.eu/corporateplayer/index.html?ref=I-136289',
        'I-136289',
      ],
      'Old URL - http' => [
        'http://ec.europa.eu/avservices/play.cfm?ref=I136289',
        'I-136289',
      ],
      'Old URL - https' => [
        'https://ec.europa.eu/avservices/play.cfm?ref=I136289',
        'I-136289',
      ],
      'Old URL - double slash' => [
        '//ec.europa.eu/avservices/play.cfm?ref=I136289',
        'I-136289',
      ],
      'Old URL - no protocol' => [
        'ec.europa.eu/avservices/play.cfm?ref=I136289',
        'I-136289',
      ],
      'Old URL - additional params' => [
        'http://ec.europa.eu/avservices/play.cfm?a=x&ref=I136289&b=y',
        'I-136289',
      ],
      'Old URL - additional params and fragment' => [
        'http://ec.europa.eu/avservices/play.cfm?a=x&ref=I136289&b=y#whatever',
        'I-136289',
      ],
      'Old embed URL - http' => [
        'http://audiovisual.ec.europa.eu/embed/index.html?ref=I-136289',
        'I-136289',
      ],
      'Old embed URL - https' => [
        'https://audiovisual.ec.europa.eu/embed/index.html?ref=I-136289',
        'I-136289',
      ],
      'Old embed URL - double slash' => [
        '//audiovisual.ec.europa.eu/embed/index.html?ref=I-136289',
        'I-136289',
      ],
      'Old embed URL - no protocol' => [
        'audiovisual.ec.europa.eu/embed/index.html?ref=I-136289',
        'I-136289',
      ],
      'Old embed URL - additional params' => [
        'http://audiovisual.ec.europa.eu/embed/index.html?a=x&ref=I-136289&b=y',
        'I-136289',
      ],
      'Old embed URL - additional params and fragment' => [
        'http://audiovisual.ec.europa.eu/embed/index.html?a=x&ref=I-136289&b=y#whatever',
        'I-136289',
      ],
    ];
  }

}
