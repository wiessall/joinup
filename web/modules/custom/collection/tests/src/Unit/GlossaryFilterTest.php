<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_collection\Unit;

use Drupal\Component\Utility\Html;
use Drupal\collection\Plugin\Filter\Glossary;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the collection glossary filter.
 */
#[Group('joinup_collection')]
#[CoversClass(\Drupal\collection\Plugin\Filter\Glossary::class)]
class GlossaryFilterTest extends TestCase {

  /**
   * Tests the ::isLinkText() method.
   *
   * @param string $markup
   *   Markup to be tested.
   * @param bool $expectation
   *   The method return expectation.
   *
   * @throws \ReflectionException
   *   If creating the reflection class fails.
   */
  #[DataProvider('provideTestIsLinkTextDate')]
  public function testIsLinkText(string $markup, bool $expectation): void {
    $assert_method = $expectation ? 'assertTrue' : 'assertFalse';

    $filter = $this->createMock(Glossary::class);

    // Make ::isLinkText() accessible.
    $method = (new \ReflectionClass($filter))->getMethod('isLinkText');
    $method->setAccessible(TRUE);

    $doc = Html::load($markup);
    $textNode = (new \DOMXPath($doc))->evaluate("//text()")[0];

    $this->{$assert_method}($method->invokeArgs($filter, [$textNode]));
  }

  /**
   * Provides test cases for ::testIsLinkText() test.
   *
   * @return array[]
   *   A list of test cases.
   */
  public static function provideTestIsLinkTextDate(): array {
    return [
      'plain' => [
        'The quick brown fox jumps over the lazy dog',
        FALSE,
      ],
      'with markup' => [
        '<div><strong><span>The quick brown fox jumps over the lazy dog</span></strong></div>',
        FALSE,
      ],
      'simple link' => [
        '<a href="http://example.com">The quick brown fox jumps over the lazy dog</a>',
        TRUE,
      ],
      'link with markup label' => [
        '<a href="http://example.com"><div><strong><span>The quick brown fox jumps over the lazy dog</span></strong></div></a>',
        TRUE,
      ],
    ];
  }

}
