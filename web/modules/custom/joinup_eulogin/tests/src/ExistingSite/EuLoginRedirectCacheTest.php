<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_eulogin\ExistingSite;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the EU Login redirect is not shared between anonymous visitors.
 *
 * The /eulogin route returns a cacheable redirect, but two subscribers make
 * that response request-specific:
 * - JoinupEuLoginCasEventsSubscriber::handleBrowserCache() appends a random
 *   parameter to the destination.
 * - EuLoginEventSubscriber::forceTwoFactorAuthentication() adds
 *   'authenticationLevel' based on oe_authentication.settings, without
 *   declaring that config as a cache dependency.
 *
 * Neither is reflected in the response cacheability, so a single response is
 * stored and handed to every anonymous visitor for the lifetime of the page
 * cache. On production, where a CDN caches on top, one visitor's redirect is
 * served to everybody.
 *
 * @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::handleBrowserCache()
 * @see \Drupal\oe_authentication\Event\EuLoginEventSubscriber::forceTwoFactorAuthentication()
 */
#[Group('joinup_eulogin')]
class EuLoginRedirectCacheTest extends JoinupExistingSiteTestBase {

  /**
   * The EU Login entry point, as linked from every page.
   */
  private const string LOGIN_PATH = '/eulogin';

  /**
   * The destination carried by the Sign in link on the front page.
   */
  private const array LOGIN_QUERY = ['query' => ['destination' => '/home']];

  /**
   * Shape of the one-time value: a three-character name and six hex digits.
   *
   * @see \Drupal\joinup_eulogin\Event\Subscriber\JoinupEuLoginCasEventsSubscriber::handleBrowserCache()
   */
  private const string TOKEN_PATTERN = '#[?&]([a-z0-9]{3}=[0-9a-f]{6})#';

  /**
   * The login redirect must not carry a random value.
   *
   * The assertion is that no random value is present at all, rather than that
   * two visitors get different ones. CasRedirector only honours
   * CasRedirectData::setIsCacheable(FALSE) when it is not forcing a redirect,
   * and /eulogin always forces one, so this response cannot be made
   * uncacheable from a subscriber. Given that it will be cached and shared, the
   * only safe property is that there is nothing request-specific in it.
   *
   * @see \Drupal\cas\Service\CasRedirector::buildRedirectResponse()
   */
  public function testRedirectCarriesNoRandomValue(): void {
    $location = $this->getRedirectLocation();
    // The destination is doubly encoded inside the CAS 'service' parameter.
    $decoded = urldecode(urldecode($location));

    $this->assertDoesNotMatchRegularExpression(
      self::TOKEN_PATTERN,
      $decoded,
      'The login redirect carries a random one-time value. This response is cached and shared, so that value is frozen and handed to every subsequent visitor.',
    );
  }

  /**
   * Two anonymous visitors must not receive the same one-time value.
   */
  public function testRedirectIsNotSharedBetweenVisitors(): void {
    $first = $this->getDestinationToken($this->getRedirectLocation());
    $second = $this->getDestinationToken($this->getRedirectLocation());

    if ($first === '' && $second === '') {
      $this->addToAssertionCount(1);
      return;
    }

    $this->assertNotSame(
      $first,
      $second,
      'Two anonymous visitors received the same one-time destination token, which means a single cached redirect is being shared between them.',
    );
  }

  /**
   * A change to oe_authentication.settings must reach anonymous visitors.
   */
  public function testTwoFactorSettingChangeIsNotCached(): void {
    $config = \Drupal::configFactory()->getEditable('oe_authentication.settings');
    $original = $config->get('force_2fa');

    $config->set('force_2fa', TRUE)->save();
    $this->assertStringContainsString(
      'authenticationLevel=MEDIUM',
      $this->getRedirectLocation(),
      'Two-factor enforcement is not reflected in the login redirect.',
    );

    // Turn enforcement off, exactly as an administrator would, and without
    // rebuilding caches.
    $config->set('force_2fa', FALSE)->save();
    $location = $this->getRedirectLocation();

    // Restore before asserting, so a failure cannot leave 2FA disabled.
    $config->set('force_2fa', $original)->save();

    $this->assertStringNotContainsString(
      'authenticationLevel=MEDIUM',
      $location,
      'Two-factor enforcement was switched off but anonymous visitors still receive the cached redirect that enforces it.',
    );
  }

  /**
   * Follows the login path and returns the EU Login URL it lands on.
   *
   * The redirect is followed rather than inspected, because the CAS server URL
   * that the visitor ends up on is what carries both the authentication level
   * and the one-time destination token.
   */
  protected function getRedirectLocation(): string {
    $this->drupalGet(self::LOGIN_PATH, self::LOGIN_QUERY);
    $url = $this->getSession()->getCurrentUrl();

    $this->assertStringContainsString(
      '/login?',
      $url,
      'The login path did not lead to the EU Login server.',
    );
    return $url;
  }

  /**
   * Extracts the one-time destination token, or '' when there is none.
   *
   * ::handleBrowserCache() appends the token under a randomly named parameter,
   * so it is matched by shape rather than by name.
   */
  protected function getDestinationToken(string $location): string {
    // The destination is doubly encoded inside the CAS 'service' parameter.
    $decoded = urldecode(urldecode($location));
    return preg_match(self::TOKEN_PATTERN, $decoded, $matches) ? $matches[1] : '';
  }

}
