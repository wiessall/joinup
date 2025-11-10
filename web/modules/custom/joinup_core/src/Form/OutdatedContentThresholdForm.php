<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\joinup_core\Entity\OutdatedContentInterface;

/**
 * Provides a UI to edit the 'joinup_core.outdated_content_threshold' config.
 */
class OutdatedContentThresholdForm extends ConfigFormBase {

  use AutowireTrait;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('joinup_core.outdated_content_threshold');

    $form['help'] = [
      '#markup' => $this->t('In order to show the outdated content notice on a type of content, select the checkbox and enter the number of years after such content is marked as outdated.'),
      '#prefix' => '<p>',
      '#suffix' => '</p>',
    ];

    $form['config'] = [
      '#type' => 'table',
      '#header' => [
        'label' => $this->t('Type of content'),
        'enabled' => $this->t('Allow labeling as outdated?'),
        'threshold' => $this->t('Label after (years)'),
      ],
    ];

    foreach ($this->bundleInfo->getAllBundleInfo() as $entity_type_id => $bundles) {
      $entity_type_label = $this->entityTypeManager->getDefinition($entity_type_id)->getLabel();
      foreach ($bundles as $bundle => $info) {
        $class = $info['class'] ?? NULL;
        if ($class && is_subclass_of($class, OutdatedContentInterface::class)) {
          $threshold = $config->get("thresholds.$entity_type_id.$bundle");
          $form['config']["$entity_type_id:$bundle"] = [
            'label' => [
              '#markup' => $this->t('@bundle (@type)', [
                '@bundle' => $info['label'],
                '@type' => $entity_type_label,
              ]),
            ],
            'enabled' => [
              '#type' => 'checkbox',
              '#default_value' => !empty($threshold),
              '#title' => $this->t('Allow labeling as outdated?'),
              '#title_display' => 'invisible',
            ],
            'threshold' => [
              '#type' => 'number',
              '#title' => $this->t('Years'),
              '#title_display' => 'invisible',
              '#min' => 1,
              '#default_value' => $threshold,
              '#states' => [
                'enabled' => [
                  ":input[name='config[{$entity_type_id}:{$bundle}][enabled]']" => [
                    'checked' => TRUE,
                  ],
                ],
              ],
            ],
          ];
        }
      }
    }

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->getEditable('joinup_core.outdated_content_threshold');
    foreach ($form_state->getValue('config') as $row_id => $row) {
      [$entity_type_id, $bundle] = explode(':', $row_id, 2);
      $threshold = $row['enabled'] ? $row['threshold'] : NULL;
      $config->set("thresholds.$entity_type_id.$bundle", $threshold);
    }
    $config->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['joinup_core.outdated_content_threshold'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'outdated_content_threshold';
  }

}
