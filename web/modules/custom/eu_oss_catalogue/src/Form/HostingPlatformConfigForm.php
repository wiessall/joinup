<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\eu_oss_catalogue\Entity\HostingPlatformConfig;
use Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Edit form for 'hosting_platform' config entities.
 */
class HostingPlatformConfigForm extends EntityForm {

  use AjaxFormHelperTrait;

  /**
   * Constructs a new form instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The code hosting platform plugin manager.
   */
  public function __construct(
    protected EuOssCataloguePluginManagerInterface $platformManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $formState): array {
    $form = parent::form($form, $formState);

    $form['label'] = [
      '#title' => $this->t('Label'),
      '#type' => 'textfield',
      '#default_value' => $this->getEntity()->label(),
      '#description' => $this->t('A label for this configuration.'),
      '#required' => TRUE,
      '#size' => 30,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $this->getEntity()->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#disabled' => !$this->getEntity()->isNew(),
      '#machine_name' => [
        'exists' => [HostingPlatformConfig::class, 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique string identifier. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $this->getEntity()->getDescription(),
    ];

    $platformId = $this->getEntity()->get('platformId')
      ?? $this->getRequest()->query->get('platformId');

    $form['platformId'] = [
      '#type' => 'select',
      '#title' => $this->t('Code hosting platform'),
      '#options' => $this->platformManager->getDefinitionsAsOptions('label'),
      '#default_value' => $platformId,
      '#required' => TRUE,
      '#ajax' => ['callback' => '::ajaxSubmit'],
      '#disabled' => !$this->getEntity()->isNew(),
    ];

    if ($this->isAjax()) {
      // @see https://www.drupal.org/node/2897377
      $form['#id'] = Html::getId($formState->getBuildInfo()['form_id']);
    }

    $form['platformSettings'] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];

    $this->askPluginToAct('build', $platformId, $form, $formState);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    parent::validateForm($form, $formState);

    // Remove leading or trailing whitespaces.
    $formState->setValue('id', trim($formState->getValue('id')));
    $formState->setValue('label', trim($formState->getValue('label')));
    $formState->setValue('description', trim($formState->getValue('description')));

    // Ask plugin to add its validations.
    $this->askPluginToAct('validate', $formState->getValue('platformId'), $form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    parent::submitForm($form, $formState);
    if ($plugin = $this->askPluginToAct('submit', $formState->getValue('platformId'), $form, $formState)) {
      $configuration = $plugin->getConfiguration();
      unset($configuration['hosting_platform']);
      $this->getEntity()->set('platformSettings', $configuration);
    }
  }

  /**
   * Applies plugin actions against the form.
   *
   * @param string $method
   *   The method: 'build', 'validate', 'submit'.
   * @param string|null $platformId
   *   The code hosting platform plugin ID. Is NULL on new entities.
   * @param array $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  protected function askPluginToAct(string $method, ?string $platformId, array &$form, FormStateInterface $formState): ?EuOssCatalogueHostingPlatformInterface {
    // A plugin ID doesn't exist yet. It happens on a new config entity.
    if (!$platformId) {
      return NULL;
    }
    \assert(in_array($method, ['build', 'validate', 'submit'], TRUE));

    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueHostingPlatformInterface $plugin */
    $plugin = $this->platformManager->createInstance($platformId, [
      // Pass also the entity as plugin configuration for plugins that might
      // need it. It's removed from configuration on form submit.
      'hosting_platform' => $this->getEntity(),
    ] +
    // Pass the settings if the platform already exists.
    ($this->getEntity()->isNew() ? [] : $this->getEntity()->getPlatformSettings()));

    // This plugin doesn't support forms.
    if (!$plugin instanceof PluginFormInterface) {
      return NULL;
    }

    $subFormState = SubformState::createForSubform($form['platformSettings'], $form, $formState);

    // Casting to array, as a hack, to avoid `if ($method === 'buildConf...')`.
    $method .= 'ConfigurationForm';
    $result = (array) $plugin->$method($form['platformSettings'], $subFormState);
    $form['platformSettings'] += $result;

    return $plugin;
  }

  /**
   * {@inheritdoc}
   */
  protected function successfulAjaxSubmit(array $form, FormStateInterface $form_state): Response {
    $selector = '[data-drupal-selector="' . $form['#attributes']['data-drupal-selector'] . '"]';
    return (new AjaxResponse())->addCommand(new ReplaceCommand($selector, $form));
  }

  /**
   * {@inheritdoc}
   */
  public function getEntity(): HostingPlatformConfigInterface {
    // Overriding the parent just to offer nice return typing.
    /** @var \Drupal\eu_oss_catalogue\Entity\HostingPlatformConfigInterface $entity */
    $entity = parent::getEntity();
    return $entity;
  }

}
