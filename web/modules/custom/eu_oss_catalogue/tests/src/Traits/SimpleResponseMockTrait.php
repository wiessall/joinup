<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_catalogue\Traits;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Trait for mocking HTTP client responses.
 *
 * @property \Symfony\Component\DependencyInjection\ContainerInterface $container
 */
trait SimpleResponseMockTrait {

  /**
   * The HTTP client mock, if any.
   *
   * @var \GuzzleHttp\ClientInterface|null
   */
  protected ?ClientInterface $mockClient;

  /**
   * Replaces the HTTP client service with a mock returning the given responses.
   *
   * @param \GuzzleHttp\Psr7\Response ...$responses
   *   List of responses to return.
   */
  protected function setGlobalMockClient(Response ...$responses): void {
    $this->mockClient = $this->buildHttpClientMock(...$responses);
    // @phpstan-ignore property.notFound
    $this->container->set('http_client', $this->mockClient);
  }

  /**
   * Builds an HTTP client service with a mock returning the given responses.
   *
   * @param \GuzzleHttp\Psr7\Response ...$responses
   *   List of responses to return.
   */
  protected function buildHttpClientMock(Response ...$responses): ClientInterface {
    $mock = new MockHandler($responses);
    $handler_stack = HandlerStack::create($mock);
    return new Client(['handler' => $handler_stack]);
  }

}
