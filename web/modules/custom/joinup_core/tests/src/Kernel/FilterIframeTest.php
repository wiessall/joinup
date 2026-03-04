<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\joinup_core\Plugin\Filter\FilterIframe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "joinup_iframe" filter.
 */
#[CoversClass(FilterIframe::class)]
#[Group('joinup_core')]
class FilterIframeTest extends KernelTestBase {

  /**
   * Mocked base URL for the test.
   */
  protected const BASE_URL = 'http://localhost/subdir';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'filter',
    'joinup_core',
    'oe_webtools_cookie_consent',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    /** @var \Symfony\Component\Routing\RequestContext $request_context */
    $request_context = $this->container->get('router.request_context');
    $request_context->setCompleteBaseUrl(self::BASE_URL);
  }

  /**
   * Tests the iframe filter processing.
   */
  #[DataProvider('providerHtml')]
  public function testProcess(string $html, string $expected, ?array $settings = []): void {
    $config = ['settings' => $settings + ['allowed_domains' => []]];
    $filter = new FilterIframe($config, 'filter_iframe', ['provider' => 'test'], $this->container->get('router.request_context'));
    $this->assertSame($expected, $filter->process($html, 'en')->getProcessedText());
  }

  /**
   * Provides data for testProcess.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerHtml(): array {
    return [
      'no iframe' => [
        'html' => '<p>Foo Bar</p>',
        'expected' => '<p>Foo Bar</p>',
      ],
      'empty iframe src' => [
        'html' => '<iframe src=""></iframe>',
        'expected' => '',
      ],
      'no iframe src' => [
        'html' => '<iframe title="foo"></iframe>',
        'expected' => '',
      ],
      'local domain' => [
        'html' => '<iframe src="/foo/bar"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="/foo/bar"></iframe></div>',
      ],
      'local domain with base URL' => [
        'html' => '<iframe src="' . self::BASE_URL . '/foo/bar"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="' . self::BASE_URL . '/foo/bar"></iframe></div>',
      ],
      'allowed domains' => [
        'html' => '<iframe src="//example1.com/embed_code/12345"></iframe><iframe src="https://www.example2.com/embed_code/67890"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="https://webtools.europa.eu/crs/iframe/?oriurl=//example1.com/embed_code/12345&amp;lang=en"></iframe></div><div class="ratio ratio-16x9"><iframe src="https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//www.example2.com/embed_code/67890&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['example1.com', '*.example2.com']],
      ],
      'not allowed domains' => [
        'html' => '<iframe src="https://untrusted.com/embed_code"></iframe><iframe src="https://very.un-trusted.com/embed_code"></iframe>',
        'expected' => '',
        'settings' => ['allowed_domains' => ['trusted.com']],
      ],
      'multiple mixed domains' => [
        'html' => '<iframe src="https://trusted.com/embed_code"></iframe><iframe src="https://untrusted.com/embed_code"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//trusted.com/embed_code&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['trusted.com']],
      ],
      'with wildcard domain' => [
        'html' => '<iframe src="https://sub.example.com/embed_code"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="https://webtools.europa.eu/crs/iframe/?oriurl=https%3A//sub.example.com/embed_code&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['*.example.com']],
      ],
    ];
  }

}
