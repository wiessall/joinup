<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_html_stripper\Unit;

use Drupal\joinup_html_stripper\Plugin\Filter\AccessibilityFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the accessibility filter.
 */
#[CoversClass(AccessibilityFilter::class)]
#[Group('joinup_html_stripper')]
class AccessibilityFilterTest extends TestCase {

  /**
   * Tests the filter processing.
   *
   * @param string $input
   *   The input HTML.
   * @param string $expected
   *   The expected output HTML.
   */
  #[DataProvider('provideTestProcessData')]
  public function testProcess(string $input, string $expected): void {
    $filter = new AccessibilityFilter([], 'joinup_accessibility_filter', ['provider' => 'joinup_html_stripper']);
    $result = $filter->process($input, 'en');
    $this->assertSame($expected, $result->getProcessedText());
  }

  /**
   * Provides test cases for the accessibility filter.
   *
   * @return array[]
   *   Array of test cases with input and expected output.
   */
  public static function provideTestProcessData(): array {
    return [
      'no relevant tags' => [
        'input' => '<div>Some content</div>',
        'expected' => '<div>Some content</div>',
      ],
      'empty paragraph' => [
        'input' => '<p></p>',
        'expected' => '',
      ],
      'empty paragraph with whitespace' => [
        'input' => '<p>   </p>',
        'expected' => '',
      ],
      'empty paragraph with non-breaking space' => [
        'input' => '<p>&nbsp;</p>',
        'expected' => '',
      ],
      'empty paragraph with mixed whitespace and nbsp' => [
        'input' => '<p> &nbsp; </p>',
        'expected' => '',
      ],
      'non-empty paragraph' => [
        'input' => '<p>Some content</p>',
        'expected' => '<p>Some content</p>',
      ],
      'empty h1' => [
        'input' => '<h1></h1>',
        'expected' => '',
      ],
      'empty h2 with whitespace' => [
        'input' => '<h2>   </h2>',
        'expected' => '',
      ],
      'empty h3 with nbsp' => [
        'input' => '<h3>&nbsp;</h3>',
        'expected' => '',
      ],
      'empty h4 with mixed whitespace' => [
        'input' => '<h4> &nbsp; </h4>',
        'expected' => '',
      ],
      'empty h5' => [
        'input' => '<h5></h5>',
        'expected' => '',
      ],
      'empty h6' => [
        'input' => '<h6></h6>',
        'expected' => '',
      ],
      'non-empty heading' => [
        'input' => '<h1>Title</h1>',
        'expected' => '<h1>Title</h1>',
      ],
      'mixed empty and non-empty tags' => [
        'input' => '<p></p><h1>Title</h1><p>Content</p><h2></h2>',
        'expected' => '<h1>Title</h1><p>Content</p>',
      ],
      'multiple empty paragraphs' => [
        'input' => '<p></p><p>   </p><p>&nbsp;</p>',
        'expected' => '',
      ],
      'multiple empty headings' => [
        'input' => '<h1></h1><h2>   </h2><h3>&nbsp;</h3><h4> &nbsp; </h4><h5></h5><h6></h6>',
        'expected' => '',
      ],
      'nested content with empty tags' => [
        'input' => '<div><p></p><span>Text</span><h1></h1></div>',
        'expected' => '<div><span>Text</span></div>',
      ],
      'paragraph with only nested empty elements' => [
        'input' => '<p><span></span><strong></strong></p>',
        'expected' => '',
      ],
      'heading with attributes but empty' => [
        'input' => '<h1 id="test" class="heading"></h1>',
        'expected' => '',
      ],
      'paragraph with attributes but empty' => [
        'input' => '<p class="lead" id="intro">   </p>',
        'expected' => '',
      ],
      'complex mixed content' => [
        'input' => '<h1>Real Title</h1><p></p><h2>Subtitle</h2><p>Content here</p><h3></h3><p>&nbsp;</p>',
        'expected' => '<h1>Real Title</h1><h2>Subtitle</h2><p>Content here</p>',
      ],
      'unicode non-breaking space' => [
        'input' => '<p> </p>',
        'expected' => '',
      ],
      'mixed whitespace types' => [
        'input' => '<p>	&nbsp; </p>',
        'expected' => '',
      ],
    ];
  }

}
