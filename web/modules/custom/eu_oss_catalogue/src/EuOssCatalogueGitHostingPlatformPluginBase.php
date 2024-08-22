<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\multivalue_form_element\Element\MultiValue;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Git code hosting plugin platforms (GitHub, GitLab, etc).
 */
abstract class EuOssCatalogueGitHostingPlatformPluginBase extends EuOssCatalogueHostingPlatformPluginBase implements PluginFormInterface, ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param array $plugin_definition
   *   The plugin definition.
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ClientInterface $httpClient,
    protected Settings $settings,
    protected LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('settings'),
      $container->get('logger.channel.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'projects' => [],
      'baseUrl' => $this->getPluginDefinition()['baseUrl'] ?? NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['baseUrl'] = [
      '#type' => 'url',
      '#title' => $this->t('URL'),
      '#default_value' => $this->getConfiguration()['baseUrl'],
      '#required' => TRUE,
    ];

    $form['projects'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Projects'),
      '#description' => $this->t('If name is omitted, all projects from that namespace are eligible.'),
      '#default_value' => $this->getProjectsDefaultValue(),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#required' => TRUE,
      'namespace' => [
        '#type' => 'textfield',
        '#title' => $this->t('Namespace'),
        '#required' => TRUE,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    $triggeringElementName = $formState->getTriggeringElement()['#name'] ?? NULL;
    if ($triggeringElementName !== 'op') {
      // Do validation only when the whole form is submitted.
      return;
    }

    $projects = $formState->getValue('projects', []);
    array_walk($projects, function (array &$candidate): void {
      if (empty($candidate['namespace'])) {
        $candidate = [];
        return;
      }
      $candidate['namespace'] = trim($candidate['namespace']);
      if ($candidate['name']) {
        $candidate['name'] = trim($candidate['name']);
      }
      // @todo Validate $candidate['namespace'] & $candidate['name'] for naming
      //   conventions.
    });

    // The values might have been trimmed earlier.
    $formState->setValue('projects', array_filter($projects));
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $baseUrl = $form_state->getValue('baseUrl');
    $this->configuration['baseUrl'] = is_string($baseUrl) ? trim($form_state->getValue('baseUrl'), ' /') : NULL;

    // Converts from:
    // @code
    // [
    //   ['namespace' => 'namespace1', 'name' => 'name1'],
    //   ['namespace' => 'namespace1', 'name' => 'name2'],
    //   ['namespace' => 'namespace2', 'name' => ''],
    // ]
    // @endcode
    // to:
    // @code
    // [
    //   'namespace1' => ['name1', 'name2'],
    //   'namespace2' => NULL,
    // ]
    // @endcode
    $projects = [];
    foreach ($form_state->getValue('projects', []) as $row) {
      $projects[$row['namespace']] ??= [];
      if ($row['name']) {
        $projects[$row['namespace']][] = $row['name'];
      }
    }
    // NULL denotes a namespace where all projects should be fetched.
    $projects = array_map(fn(array $names): ?array => $names ?: NULL, $projects);

    $this->configuration['projects'] = $projects;
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  final public function getFileRawUrl(string $url, string $projectUrl): ?string {
    if ($this->isFileRawUrl($url, $projectUrl)) {
      return $url;
    }

    // Relative path?
    if (!preg_match('@^https?://@', $url)) {
      $url = $projectUrl . '/' . ltrim($url, '/');
    }
    elseif (!str_starts_with($url, $this->getConfiguration()['baseUrl'])) {
      // This file is not hosted by this platform.
      return $url;
    }
    return $this->doGetFileRawUrl($url, $projectUrl);
  }

  /**
   * {@inheritdoc}
   */
  public function getProjects(array $requestedFiles = []): iterable {
    \assert($requestedFiles === array_unique($requestedFiles));
    foreach ($this->doGetProjects($requestedFiles) as $project) {
      yield $project;
    }
  }

  /**
   * Returns a file raw URL given a relative path or URL.
   *
   * @param string $url
   *   A file path relative to the repository root or a full URL.
   * @param string $projectUrl
   *   The project homepage URL.
   *   A branch, commit or a tag.
   *
   * @return string|null
   *   The file raw URL or NULL if it cannot be resolved.
   */
  abstract protected function doGetFileRawUrl(string $url, string $projectUrl): ?string;

  /**
   * Check whether the passed URL represents a file raw URL.
   *
   * @param string $url
   *   The URL to be checked.
   * @param string $projectUrl
   *   The project homepage URL.
   *   A branch or a tag.
   *
   * @return bool
   *   Whether the passed URL is a file raw URL.
   */
  abstract protected function isFileRawUrl(string $url, string $projectUrl): bool;

  /**
   * Returns the list of projects.
   *
   * Subclasses should implement this method, instead of ::getProjects(), to
   * build the list of projects.
   *
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   *
   * @return iterable
   *   Array \Drupal\eu_oss_catalogue\Model\Project instances.
   */
  abstract protected function doGetProjects(array $requestedFiles = []): iterable;

  /**
   * Prepares the projects list to be used by multivalue element.
   *
   * @return array
   *   The projects list.
   */
  protected function getProjectsDefaultValue(): array {
    // Converts from:
    // @code
    // [
    //   'namespace1' => ['name1', 'name2'],
    //   'namespace2' => NULL,
    // ]
    // @endcode
    // to:
    // @code
    // [
    //   ['namespace' => 'namespace1', 'name' => 'name1'],
    //   ['namespace' => 'namespace1', 'name' => 'name2'],
    //   ['namespace' => 'namespace2', 'name' => ''],
    // ]
    // @endcode
    $projects = [];
    foreach ($this->getConfiguration()['projects'] ?? [] as $namespace => $names) {
      if ($names === NULL) {
        $projects[] = ['namespace' => $namespace, 'name' => ''];
        continue;
      }
      foreach ($names as $name) {
        $projects[] = ['namespace' => $namespace, 'name' => $name];
      }
    }

    return $projects;
  }

}
