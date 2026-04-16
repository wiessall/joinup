<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Driver\BrowserKitDriver;
use Behat\Mink\Driver\Selenium2Driver;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Exception\ExpectationException;
use Behat\Mink\Exception\UnsupportedDriverActionException;
use Behat\Step\Then;
use Drupal\Core\Site\Settings;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\EntityTrait;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;

/**
 * Defines generic step definitions.
 */
class LinkContext extends RawDrupalContext {

  use EntityTrait;

  /**
   * Asserts that a file downloaded from a link contains a list of strings.
   *
   * IMPORTANT NOTE: This step definition is not performing any file access
   * check. The file content is read directly from the file system. The user
   * access to the file should be tested in separate Behat steps.
   *
   * @param string $link_label
   *   The link from where to download the file.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @throws \Exception
   *     If the link has no href attribute or the file content cannot be loaded.
   */
  #[Then('the file downloaded from the :link_label link contains the following strings:')]
  public function assertDownloadedFileContainsStrings(string $link_label, TableNode $strings_table): void {
    $url = $this->getUrl($link_label);

    // Get the path part from the URL.
    $path = rtrim(parse_url($url, PHP_URL_PATH), '/');
    // Remove the base path.
    $path = substr($path, strlen(base_path()));

    // Drupal private file.
    if (str_starts_with($path, 'system/files')) {
      $path = Settings::get('file_private_path') . substr($path, 12);
    }
    // Webserver accessible file.
    else {
      $path = DRUPAL_ROOT . "/{$path}";
    }

    if (($content = file_get_contents($path)) === FALSE) {
      throw new \Exception("Cannot read '{$path}' file.");
    }

    if (!$content) {
      throw new \Exception("The downloaded file has no content.");
    }

    $not_found = $this->searchMissingContent($content, $strings_table);

    if (!empty($not_found)) {
      throw new ExpectationException("Following strings were not found in the downloaded file:\n- " . implode("\n- ", $not_found), $this->getSession()->getDriver());
    }
  }

  /**
   * Asserts that the linked content contains a list of strings.
   *
   * @param string $link_label
   *   The link from where to get the content.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @throws \Exception
   *   If the link has no href attribute or the file content cannot be loaded.
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the connection to the link fails.
   */
  #[Then('the content received from clicking the link :link_label contains the following strings:')]
  public function assertLinkedContentContainsStrings(string $link_label, TableNode $strings_table): void {
    $url = $this->getUrl($link_label);

    // Send request.
    $client = new Client();
    $response = $client->get($url, $this->getSessionOptions($url));
    $content = $response->getBody()->getContents();
    if (empty($content)) {
      throw new \Exception("The downloaded file has no content.");
    }

    if ($not_found = $this->searchMissingContent($content, $strings_table)) {
      throw new ExpectationException("Following strings were not found in the linked content:\n- " . implode("\n- ", $not_found), $this->getSession()->getDriver());
    }
  }

  /**
   * Asserts that the linked content does not contain a list of strings.
   *
   * @param string $link_label
   *   The link from where to get the content.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @throws \Exception
   *   If the link has no href attribute or the file content cannot be loaded.
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the connection to the link fails.
   */
  #[Then('the content received from clicking the link :link_label does not contain the following strings:')]
  public function assertLinkedContentNotContainsStrings(string $link_label, TableNode $strings_table): void {
    $url = $this->getUrl($link_label);

    // Send request.
    $client = new Client();
    $response = $client->get($url, $this->getSessionOptions($url));
    $content = $response->getBody()->getContents();
    if (empty($content)) {
      throw new \Exception("The downloaded file has no content.");
    }

    if ($found = array_diff($strings_table->getColumn(0), $this->searchMissingContent($content, $strings_table))) {
      throw new ExpectationException("Following strings were found in the linked content:\n- " . implode("\n- ", $found), $this->getSession()->getDriver());
    }
  }

  /**
   * Get the referred URL in a link.
   *
   * @param string $link_label
   *   The link where the URL can be found.
   *
   * @return string
   *   The found URL.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException|\Exception
   *    If the link has no href attribute or the file content cannot be loaded.
   */
  protected function getUrl(string $link_label): string {
    if (!$link = $this->getSession()->getPage()->findLink($link_label)) {
      throw new ElementNotFoundException($this->getSession()->getDriver(), 'Link', NULL, $link_label);
    }
    if (!$url = $link->getAttribute('href')) {
      throw new \Exception("The link '{$link_label}' misses an 'href' attribute.");
    }

    if (!str_starts_with($url, $GLOBALS['base_url'])) {
      $url = $GLOBALS['base_url'] . $url;
    }

    return $url;
  }

  /**
   * Perform the tests comparing with the expected results.
   *
   * @param string $content
   *   The content to compare.
   * @param \Behat\Gherkin\Node\TableNode $strings_table
   *   A table with a single column. Each row contains a string.
   *
   * @return array
   *   The missing rows.
   */
  protected function searchMissingContent(string $content, TableNode $strings_table): array {
    $not_found = [];
    foreach ($strings_table->getColumn(0) as $text) {
      $matches = [];
      if (preg_match('/^.*%(.*?)%.*$/', $text, $matches)) {
        $entity = $this->getEntityByLabel('node', $matches[1]);
        $text = str_replace("%{$matches[1]}%", $entity->id(), $text);
      }
      if (!str_contains($content, $text)) {
        $not_found[] = $text;
      }
    }
    return $not_found;
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
      throw new UnsupportedDriverActionException('Session cookie retrieval is not supported by %s', $driver);
    }

    $cookie_jar = CookieJar::fromArray([$session_name => $session_id], $domain);
    return ['cookies' => $cookie_jar];
  }

}
