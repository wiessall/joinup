<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Site\Settings;
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
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginFefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginFefinition,
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
      '#default_value' => $this->getConfiguration()['projects'],
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

    $candidates = $formState->getValue('projects', []);
    array_walk($candidates, function (array &$candidate) {
      if (empty($candidate['namespace'])) {
        $candidate = [];
        return;
      }
      $candidate['namespace'] = trim($candidate['namespace']);
      if ($candidate['name']) {
        $candidate['name'] = trim($candidate['name']);
      }
    });
    $candidates = array_filter($candidates);
    $existingProjects = $this->getProjects($candidates);

    // The values might have been trimmed earlier.
    $formState->setValue('projects', $candidates);

    foreach ($candidates as $delta => $candidate) {
      if (!isset($existingProjects[$candidate['namespace']])) {
        $formState->setErrorByName("projects][$delta][namespace", $this->t("Namespace %namespace doesn't exist on the platform", [
          '%namespace' => $candidate['namespace'],
        ]));
      }
      elseif ($candidate['name'] && !isset($existingProjects[$candidate['namespace']][$candidate['name']])) {
        $formState->setErrorByName("projects][$delta][name", $this->t("Project %project doesn't exist on the platform or it's not public", [
          '%project' => $candidate['namespace'] . '/' . $candidate['name'],
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    $this->configuration['projects'] = $formState->getValue('projects');
  }

  /**
   * {@inheritdoc}
   */
  public function getRepositories(?string $filePath = NULL): iterable {
    foreach ($this->getProjects($this->getConfiguration()['projects'], $filePath) as $projects) {
      foreach ($projects as $result) {
        yield $result;
      }
    }
  }

  /**
   * Returns a full list of projects.
   *
   * This method expands a project candidate when only the vendor has been
   * specified by querying the code platform API. By passing a relevant file
   * path, relative to repository root, as parameter, the results will contain
   * also the file content. This is useful when that file contains metadata
   * about the project.
   *
   * @param array[] $candidates
   *   A list of candidates to filter on. Each item is an associative array:
   *   - namespace: Mandatory namespace.
   *   - name: Optional project name. If omitted all public projects from the
   *     namespace will be considered.
   * @param string|null $filePath
   *   (optional) A file path relative to repository root.
   *
   * @return array
   *   Associative array:
   *   - First level keys are namespaces.
   *   - Second level keys are namespace repository project names and values are
   *     objects of type \Drupal\eu_oss_catalogue\Model\HostingPlatformResult.
   */
  abstract protected function getProjects(array $candidates, ?string $filePath = NULL): array;

}
