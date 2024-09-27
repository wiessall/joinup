<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_private_message\Kernel;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\UiHelperTrait;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

/**
 * Tests the route alterations in the PrivateMessageRouteSubscriber.
 *
 * @group joinup_private_message
 */
class PrivateMessageRouteSubscriberTest extends BrowserTestBase {

  use UiHelperTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'private_message',
    'joinup_private_message',
  ];

  /**
   * The route provider service.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected RouteProviderInterface $routeProvider;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->routeProvider = $this->container->get('router.route_provider');
  }

  /**
   * Tests that the route paths have been correctly altered.
   */
  public function testRoutePathAlterations(): void {
    $route = $this->routeProvider->getRouteByName('private_message.private_message_page');
    $this->assertNotNull($route, 'The private message page route should exist.');
    $this->assertEquals('/user/messages', $route->getPath(), 'The path for the private message page route should be "/user/messages".');

    $route = $this->routeProvider->getRouteByName('entity.private_message_thread.delete_form');
    $this->assertNotNull($route, 'The private message thread delete form route should exist.');
    $this->assertEquals('/user/messages/{private_message_thread}/delete', $route->getPath(), 'The path for the delete form should be "/user/messages/{private_message_thread}/delete".');

    $route = $this->routeProvider->getRouteByName('entity.private_message_thread.clear_personal_history_form');
    $this->assertNotNull($route, 'The clear personal history form route should exist.');
    $this->assertEquals('/user/messages/{private_message_thread}/clear-personal-history', $route->getPath(), 'The path for the clear personal history form should be "/user/messages/{private_message_thread}/clear-personal-history".');

    $route = $this->routeProvider->getRouteByName('private_message.private_message_create');
    $this->assertNotNull($route, 'The private message creation route should exist.');
    $this->assertEquals('/user/messages/message/create', $route->getPath(), 'The path for creating a private message should be "/user/messages/message/create".');

    $route = $this->routeProvider->getRouteByName('entity.private_message.delete');
    $this->assertNotNull($route, 'The private message delete route should exist.');
    $this->assertEquals('/user/messages/message/{private_message}/delete', $route->getPath(), 'The path for deleting a private message should be "/user/messages/message/{private_message}/delete".');
  }

  /**
   * Tests that specific routes have been removed.
   */
  public function testRouteRemovals(): void {
    $this->assertRouteNotFound('entity.private_message.canonical', 'The canonical private message route should be removed.');
    $this->assertRouteNotFound('private_message.ban_autocomplete', 'The ban autocomplete route should be removed.');
    $this->assertRouteNotFound('private_message.ban_user_form', 'The ban user form route should be removed.');
  }

  /**
   * Tests that the canonical private message thread route returns a 404.
   */
  public function testPrivateMessageThreadCanonicalRoute(): void {
    try {
      $route = $this->routeProvider->getRouteByName('entity.private_message_thread.canonical');

      $this->drupalGet($route->getPath());
      $this->assertSession()->statusCodeEquals(404);
    }
    catch (RouteNotFoundException $e) {
      // If the route is not found, ensure it's properly asserted.
      $this->assertTrue(TRUE, 'The canonical private message thread route does not exist, as expected.');
    }
  }

  /**
   * Asserts that a route does not exist.
   *
   * @param string $route_name
   *   The name of the route.
   * @param string $message
   *   The assertion message.
   */
  protected function assertRouteNotFound(string $route_name, string $message): void {
    try {
      $this->routeProvider->getRouteByName($route_name);
      $this->fail($message);
    }
    catch (RouteNotFoundException $e) {
      $this->assertTrue(TRUE, $message);
    }
  }

}
