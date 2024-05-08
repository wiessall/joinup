<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use PHPUnit\Framework\ExpectationFailedException;

/**
 * Defines generic step definitions.
 */
class LinkContext extends RawDrupalContext {

  /**
   * Asserts that the linked content contains a list of strings.
   *
   * @param string $link
   *   The link from where to get the content.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @throws \Exception
   *   If the link has no href attribute or the file content cannot be loaded.
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the connection to the link fails.
   *
   * @Then the content received from clicking the link :link contains the following strings:
   */
  public function assertLinkedContentContainsStrings(string $link, TableNode $strings_table): void {
    if (!$link = $this->getSession()->getPage()->findLink($link)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Link', NULL, $link);
    }
    if (!$url = $link->getAttribute('href')) {
      throw new \Exception("The link '{$link}' misses a 'href' attribute.");
    }

    // Send request.
    $client = new Client();
    $response = $client->get($url, $this->getSessionOptions($url));
    $content = $response->getBody()->getContents();
    if (empty($content)) {
      throw new \Exception("The downloaded file has no content.");
    }

    // Perform the tests comparing with the expected results.
    $not_found = [];
    foreach ($strings_table->getColumn(0) as $text) {
      $matches = [];
      if (preg_match('/^.*%(.*?)%.*$/', $text, $matches)) {
        $entity = $this->getEntityByLabel('rdf_entity', $matches[1]);
        $text = str_replace("%{$matches[1]}%", $entity->id(), $text);
      }
      if (strpos($content, $text) === FALSE) {
        $not_found[] = $text;
      }
    }
    if (!empty($not_found)) {
      throw new ExpectationFailedException("Following strings were not found in the linked content:\n- " . implode("\n- ", $not_found));
    }
  }

  /**
   * Prepare request options with session cookies.
   *
   * @param string $url
   *   The current URL.
   *
   * @return array
   *   The options array with session cookies.
   *
   * @throws \Behat\Mink\Exception\UnsupportedDriverActionException
   * @throws \Behat\Mink\Exception\DriverException
   */
  protected function getSessionOptions(string $url): array {
    $domain = parse_url($url, PHP_URL_HOST);

    // Get session data.
    $driver = $this->getSession()->getDriver();
    if ($driver instanceof BrowserKitDriver) {
      /** @var \Symfony\Component\BrowserKit\Cookie $cookie */
      $cookie = $driver->getClient()->getCookieJar()->all()[0];
      $session_name = $cookie->getName();
      $session_id = $cookie->getValue();
    }
    elseif ($driver instanceof Selenium2Driver) {
      // This may be handy when using the @javascript tag in the test.
      $session = $driver->getWebDriverSession();
      $cookie = new SetCookie($session->getCookie()[0]);
      $jar = new CookieJar();
      $jar->setCookie($cookie);
      $session_name = $cookie->toArray()['name'];
      $session_id = $this->getSession()->getCookie($session_name);
    }
    else {
      throw new UnsupportedDriverActionException();
    }

    $cookie_jar = CookieJar::fromArray([$session_name => $session_id], $domain);
    return ['cookies' => $cookie_jar];
  }

}
