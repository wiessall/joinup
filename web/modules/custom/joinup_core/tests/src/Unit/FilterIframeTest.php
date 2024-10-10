<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\PathProcessor\OutboundPathProcessorInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Utility\UnroutedUrlAssembler;
use Drupal\Tests\UnitTestCase;
use Drupal\joinup_core\Plugin\Filter\FilterIframe;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Tests the "joinup_iframe" filter.
 *
 * @coversDefaultClass \Drupal\joinup_core\Plugin\Filter\FilterIframe
 */
final class FilterIframeTest extends UnitTestCase {

  /**
   * Fake base URL.
   */
  const BASE_URL = 'https://example.com/path';

  /**
   * The request context service.
   *
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * {@inheritdoc}
   */
  public function setup(): void {
    parent::setup();

    // Mock URL assembler.
    $request_stack = new RequestStack();
    $request_stack->push(new Request());
    $path_processor = $this->createMock(OutboundPathProcessorInterface::class);
    $url_assembler = new UnroutedUrlAssembler($request_stack, $path_processor);

    $container = new ContainerBuilder();
    $container->set('unrouted_url_assembler', $url_assembler);
    \Drupal::setContainer($container);

    // Mock request context.
    $this->requestContext = $this->createMock(RequestContext::class);
    $this->requestContext->expects($this->any())
      ->method('getCompleteBaseUrl')
      ->willReturn(self::BASE_URL);
  }

  /**
   * @covers ::process
   *
   * @dataProvider providerHtml
   */
  public function testProcess(string $html, string $expected, ?array $settings = []): void {
    $config = ['settings' => $settings + ['allowed_domains' => []]];
    $filter = new FilterIframe($config, 'filter_iframe', ['provider' => 'test'], $this->requestContext);
    $this->assertSame($expected, $filter->process($html, 'en')->getProcessedText());
  }

  /**
   * Provides data for testProcess.
   *
   * @return array
   *   An array of test data.
   */
  public static function providerHtml(): array {
    $base_url = self::BASE_URL;
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
        'html' => "<iframe src=\"$base_url/foo/bar\"></iframe>",
        'expected' => "<div class=\"ratio ratio-16x9\"><iframe src=\"$base_url/foo/bar\"></iframe></div>",
      ],
      'allowed domains' => [
        'html' => '<iframe src="//example1.com/embed_code/12345"></iframe><iframe src="https://www.example2.com/embed_code/67890"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="//webtools.europa.eu/crs/iframe/?oriurl=//example1.com/embed_code/12345&amp;lang=en"></iframe></div><div class="ratio ratio-16x9"><iframe src="//webtools.europa.eu/crs/iframe/?oriurl=https%3A//www.example2.com/embed_code/67890&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['example1.com', '*.example2.com']],
      ],
      'not allowed domains' => [
        'html' => '<iframe src="https://untrusted.com/embed_code"></iframe><iframe src="https://very.un-trusted.com/embed_code"></iframe>',
        'expected' => '',
        'settings' => ['allowed_domains' => ['trusted.com']],
      ],
      'multiple mixed domains' => [
        'html' => '<iframe src="https://trusted.com/embed_code"></iframe><iframe src="https://untrusted.com/embed_code"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="//webtools.europa.eu/crs/iframe/?oriurl=https%3A//trusted.com/embed_code&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['trusted.com']],
      ],
      'with wildcard domain' => [
        'html' => '<iframe src="https://sub.example.com/embed_code"></iframe>',
        'expected' => '<div class="ratio ratio-16x9"><iframe src="//webtools.europa.eu/crs/iframe/?oriurl=https%3A//sub.example.com/embed_code&amp;lang=en"></iframe></div>',
        'settings' => ['allowed_domains' => ['*.example.com']],
      ],
    ];
  }

}
