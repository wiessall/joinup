<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint;

use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Service to access the SPARQL read-only endpoint.
 */
class SparqlReadOnlyEndpoint implements SparqlReadOnlyEndpointInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly Settings $settings,
    protected readonly ClientInterface $httpClient,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function query(string $query, ?string $accept = NULL): ResponseInterface {
    $sparql = $this->settings->get('sparql');
    return $this->httpClient->get($this->getUrl(), [
      RequestOptions::QUERY => ['query' => $query],
      RequestOptions::AUTH => [
        $sparql['user_readonly'],
        $sparql['password_readonly'],
        // HTTP digest authentication.
        'digest',
      ],
      RequestOptions::HEADERS => $accept ? ['Accept' => $accept] : [],
    ]);
  }

  /**
   * Returns the state of endpoint as a requirements entry.
   *
   * @return array
   *   The state of SPARQL read-only endpoint with the same format as an entry
   *   from hook_requirements().
   *
   * @see hook_requirements()
   */
  public function getRequirement(): array {
    $args = ['@user' => $this->settings->get('sparql')['user_readonly'] ?? ''];
    $severity = REQUIREMENT_ERROR;
    $value = NULL;

    try {
      $triple = '<http://subject> <http://predicate> "object"';
      $this->query("INSERT INTO <http://data> { $triple }");
      $value = $this->t("Update queries can be performed against the SPARQL read-only endpoint! Make sure that Virtuoso '@user' user has only the SPARQL_SELECT role.", $args);

      // Cleanup testing triple.
      $this->query("WITH <http://data> DELETE { $triple } WHERE { $triple }");
    }
    catch (ClientException $exception) {
      if ($exception->getCode() === 401 && str_contains($exception->getMessage(), '401 Unauthorized')) {
        $value = $this->t("The Virtuoso '@user' user doesn't exist or the password is invalid.", $args);
      }
    }
    catch (ServerException $exception) {
      if ($exception->getCode() === 500 && str_contains($exception->getMessage(), 'No permission to execute procedure DB.DBA.SPARQL_INSERT_DICT_CONTENT')) {
        $severity = REQUIREMENT_OK;
        $value = $this->t('Only read-only queries can be performed');
      }
    }
    catch (\Throwable $exception) {
      // Nothing. Just report.
    }

    // Fallback value for any other not handled exception.
    if (isset($exception) && !isset($value)) {
      $value = $this->t('Cannot query the endpoint. Message: @message', [
        '@message' => $exception->getMessage(),
      ]);
    }

    return [
      'severity' => $severity,
      'title' => $this->t('SPARQL read-only endpoint'),
      'value' => $value,
    ];
  }

  /**
   * Returns the SPARQL read-only endpoint URL.
   *
   * @return string
   *   The endpoint URL.
   */
  protected function getUrl(): string {
    $connection = $this->settings->get('sparql');
    return http_build_url([
      'scheme' => $connection['protocol'],
      'host' => $connection['hostname'],
      'port' => $connection['port'],
      'path' => 'sparql-auth',
    ]);
  }

}
