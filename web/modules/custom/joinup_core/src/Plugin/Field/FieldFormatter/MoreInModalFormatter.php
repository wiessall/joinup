<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldFormatter;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\FormatterInterface;
use Drupal\Core\Field\FormatterPluginManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Formatter returning the configured number of items, then the rest in a modal.
 *
 * @FieldFormatter(
 *   id = "joinup_core_more_in_modal",
 *   label = @Translation("Shows all items in modal"),
 * )
 */
class MoreInModalFormatter extends FormatterBase {

  /**
   * Constructs a new MoreInModalFormatter instance.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $displayRepository
   *   The entity display repository.
   * @param \Drupal\Core\Field\FormatterPluginManager $formatterManager
   *   The field formatter plugin manager.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    protected EntityDisplayRepositoryInterface $displayRepository,
    protected FormatterPluginManager $formatterManager,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ): self {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_display.repository'),
      $container->get('plugin.manager.field.formatter')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'max_items_to_show' => -1,
      'modal_link_label_single' => 'Show item',
      'modal_link_label_multiple' => 'Show all @count items',
      'modal_view_mode' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items): void {
    if ($formatter = $this->getSourceFormatter()) {
      $formatter->prepareView($entities_items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL): array {
    $entity = $items->getEntity();
    $modalLinkLabel = $this->formatPlural(
      $items->count(),
      $this->getSetting('modal_link_label_single'),
      $this->getSetting('modal_link_label_multiple'),
      [
        '@count' => $items->count(),
      ]
    );

    // Field is empty or the field in the source view mode isn't displayed.
    if (empty($formatter = $this->getSourceFormatter())) {
      return $entity->isNew()
        ? [
          [
            '#type' => 'html_tag',
            '#tag' => 'strong',
            '#value' => $this->t(
              "Formatter needs to be configured. Maybe this field is hidden in the %view-mode view mode?",
              ['%view-mode' => $this->getSetting('modal_view_mode')],
            ),
          ],
        ]
        : [];
    }

    // Recursive rendering, let's throw an exception. Might happen if the source
    // display was deleted, so it falls back to default view mode, and this
    // formatter is used in the default view mode.
    if ($formatter->getPluginId() === $this->getPluginId()) {
      throw new \LogicException('Recursive rendering in Modal formatter');
    }

    $renderable = $formatter->view($items, $langcode);
    $maxItemsToShow = (int) $this->getSetting('max_items_to_show');
    $childKeys = Element::children($renderable);
    // Layout builder preview!
    if ($entity->isNew()) {
      $renderable[reset($childKeys)] = [
        '#type' => 'link',
        '#title' => $modalLinkLabel,
        '#url' => Url::fromRoute('<current>'),
      ];
    }
    if ($entity->isNew() || $maxItemsToShow < 0 || $items->count() <= $maxItemsToShow) {
      return $renderable;
    }

    foreach (array_slice($childKeys, $maxItemsToShow) as $delta) {
      unset($renderable[$delta]);
    }

    // Add a class for the show all modal link's field item.
    $renderable['#items'][$maxItemsToShow]->_attributes = ['data-modal-link' => TRUE];
    $renderable['#theme'] = 'field__with_modal_link';
    // If the entity is new, we cannot render the field accordingly . because
    // the entity does not have ID. This might happen on Layout Builder UI.
    $url = $entity->isNew()
      ? Url::fromRoute('<current>')
      : Url::fromRoute(
        'joinup_core.field_more_in_modal',
        [
          'entityType' => $entity->getEntityTypeId(),
          'entity' => $entity->id(),
          'viewMode' => $this->getSetting('modal_view_mode'),
          'field' => $items->getName(),
        ],
      );
    $renderable[$maxItemsToShow] = [
      '#type' => 'link',
      '#title' => $modalLinkLabel,
      '#url' => $url,
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
      '#attributes' => [
        'rel' => 'nofollow',
        'class' => ['joinup-modal', 'use-ajax'],
        'data-ajax-http-method' => 'GET',
        'data-dialog-options' => Json::encode(['width' => 800]),
        'data-dialog-type' => 'modal',
        'data-joinup-modal' => TRUE,
      ],
    ];

    return $renderable;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    // Unused method, but the interface forces to implement it.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::settingsForm($form, $form_state);

    $form['max_items_to_show'] = [
      '#type' => 'number',
      '#title' => $this->t('Number of items to show without modal link'),
      '#default_value' => $this->getSetting('max_items_to_show'),
      '#min' => -1,
      '#description' => $this->t("The maximum number of items that will be shown. Use <em>-1</em> to disarm the limit. But then you don't need this formatter"),
    ];

    // Allow every view mode except self. And since we fall back to default,
    // remove that too.
    $viewModeOptions = array_diff_key(
      $this->displayRepository->getViewModeOptions($this->fieldDefinition->getTargetEntityTypeId()),
      [$this->getActiveViewMode() => 1, 'default' => 1]
    );
    $form['modal_view_mode'] = [
      '#type' => 'select',
      '#title' => $this->t('Source view mode'),
      '#default_value' => $this->getSetting('modal_view_mode'),
      '#description' => $this->t('View mode of the entity field to use for rendering. This is used to display only the first few items, and this is what will be shown in the modal.'),
      '#options' => $viewModeOptions,
      '#required' => TRUE,
    ];

    $form['translation_warning'] = [
      '#markup' => $this->t('Please be aware that the configurations below must be translated manually, by creating a language override in the corresponding collection for the appropriate entity view display, and changing the config keys there.'),
    ];

    $form['modal_link_label_single'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal opener link label if there is only one item'),
      '#default_value' => $this->getSetting('modal_link_label_single'),
      '#description' => $this->t('You can use <code>@count</code> to show the actual item count.'),
      '#required' => TRUE,
    ];

    $form['modal_link_label_multiple'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Modal opener link label if there are multiple items'),
      '#default_value' => $this->getSetting('modal_link_label_multiple'),
      '#description' => $this->t('You can use <code>@count</code> to show the actual item count.'),
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * Returns the active view mode of the current field.
   *
   * @return string
   *   The active view mode of the current field.
   */
  protected function getActiveViewMode(): string {
    $layoutBuilderSettings = $this->getThirdPartySettings('layout_builder');
    return $layoutBuilderSettings['view_mode'] ?? $this->viewMode;
  }

  /**
   * Returns the view display of the entity what is used to render the field.
   *
   * @param string $entityTypeId
   *   The entity type ID of the entity.
   * @param string $bundle
   *   The bundle (type) of the entity.
   * @param string $viewMode
   *   The view mode to look for. If the view mode does not exist or is disabled
   *   then the default view mode will be returned.
   *
   * @return \Drupal\Core\Entity\Display\EntityViewDisplayInterface
   *   The view display of the entity what is used to render the field.
   */
  protected function getViewDisplayWithFallback(
    string $entityTypeId,
    string $bundle,
    string $viewMode
  ): EntityViewDisplayInterface {
    $original = $this->displayRepository->getViewDisplay($entityTypeId, $bundle, $viewMode);

    return !$original->isNew() && $original->status()
      ? $original
      : $this->displayRepository->getViewDisplay($entityTypeId, $bundle);
  }

  /**
   * Returns the field formatter from the configured entity view display.
   *
   * @return \Drupal\Core\Field\FormatterInterface|null
   *   The formatter set for the field in the configured entity view display.
   *   Returns NULL if the field is hidden in that display.
   */
  protected function getSourceFormatter(): ?FormatterInterface {
    $sourceViewModeConfig = $this->getViewDisplayWithFallback(
      $this->fieldDefinition->getTargetEntityTypeId(),
      $this->fieldDefinition->getTargetBundle(),
      $this->getSetting('modal_view_mode')
    );
    $sourceConfig = $sourceViewModeConfig->getComponent($this->fieldDefinition->getName());
    if (empty($sourceConfig)) {
      return NULL;
    }

    return $this->formatterManager->createInstance(
      $sourceConfig['type'],
      $sourceConfig + [
        'field_definition' => $this->fieldDefinition,
        'view_mode' => $sourceViewModeConfig->getMode(),
      ]
    );
  }

}
