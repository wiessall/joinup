<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Drush\Commands;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidationResultInterface;
use Drupal\eu_oss_catalogue\Validator\PublicCodeValidatorInterface;
use Drush\Attributes as CLI;
use Drush\Commands\AutowireTrait;
use Drush\Commands\DrushCommands;
use GuzzleHttp\ClientInterface;

/**
 * Drush commands for publiccode.yml validation.
 */
class PublicCodeValidatorCommands extends DrushCommands {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PublicCodeValidatorInterface $publicCodeValidator,
    protected readonly ClientInterface $httpClient,
  ) {
    parent::__construct();
  }

  /**
   * Validates the publiccode.yml of an OSS solution node.
   */
  #[CLI\Command(name: 'eu-oss:validate-node')]
  #[CLI\Argument(name: 'nid', description: 'The OSS solution node ID')]
  public function validateNode(int $nid): int {
    $node = $this->entityTypeManager->getStorage('node')->load($nid);

    if (!$node instanceof OssSolutionInterface) {
      $this->io()
        ->error(dt('Node @nid is not an OSS solution or does not exist.', ['@nid' => $nid]));
      return DrushCommands::EXIT_FAILURE;
    }

    $validationResult = $this->publicCodeValidator->validate(
      $node->getPublicCodeYaml()
    );

    $this->io()->title(dt('Validation results for node @nid: @title', [
      '@nid' => $nid,
      '@title' => $node->label(),
    ]));

    return $this->displayValidationResult($validationResult);
  }

  /**
   * Validates publiccode.yml content from a URL.
   */
  #[CLI\Command(name: 'eu-oss:validate-url')]
  #[CLI\Argument(name: 'url', description: 'The URL to the publiccode.yml file')]
  public function validateUrl(string $url): int {
    try {
      $response = $this->httpClient->request('GET', $url);
      $publiccode = (string) $response->getBody();
    }
    catch (\Exception $e) {
      $this->io()->error(dt('Failed to fetch content from URL: @error', [
        '@error' => $e->getMessage(),
      ]));
      return DrushCommands::EXIT_FAILURE;
    }

    $validationResult = $this->publicCodeValidator->validate($publiccode);

    $this->io()
      ->title(dt('Validation results for URL: @url', ['@url' => $url]));

    return $this->displayValidationResult($validationResult);
  }

  /**
   * Displays validation result and returns appropriate exit code.
   *
   * @param \Drupal\eu_oss_catalogue\Validator\PublicCodeValidationResultInterface $validationResult
   *   The validation result.
   *
   * @return int
   *   The exit code.
   */
  protected function displayValidationResult(PublicCodeValidationResultInterface $validationResult): int {
    if ($validationResult->isValid()) {
      $this->io()->success(dt('The publiccode.yml is valid.'));
      return DrushCommands::EXIT_SUCCESS;
    }

    $this->io()->error(dt('The publiccode.yml is invalid.'));
    $errors = $validationResult->getErrors();
    $this->io()->writeln(dt('Validation errors:'));
    foreach ($errors as $error) {
      $this->io()->writeln('  - ' . $error);
    }

    return DrushCommands::EXIT_FAILURE;
  }

}
