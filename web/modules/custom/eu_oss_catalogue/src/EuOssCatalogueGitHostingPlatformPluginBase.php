<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Form\FormStateInterface;
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
   * @param string $pluginId
   *   The plugin ID.
   * @param array $pluginDefinition
   *   The plugin definition.
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected ClientInterface $httpClient,
    protected Settings $settings,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('http_client'),
      $container->get('settings'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'projects' => [],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
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
    array_walk($projects, function (array &$candidate) {
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
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
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
    foreach ($formState->getValue('projects', []) as $row) {
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
