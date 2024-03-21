<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Field\FieldWidget;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Entity\ConfigEntityStorageInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\media\MediaTypeInterface;
use Drupal\svg_image\Plugin\Field\FieldWidget\SvgImageWidget;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an image widget that allows uploading or choosing an image.
 *
 * @FieldWidget(
 *   id = "image_library_widget",
 *   label = @Translation("Image Library Widget"),
 *   field_types = {
 *     "image",
 *   },
 * )
 */
class ImageLibraryWidget extends SvgImageWidget {

  /**
   * The media type entity storage.
   */
  protected ConfigEntityStorageInterface $mediaTypeStorage;

  /**
   * Constructs a new plugin instance.
   *
   * @param string $pluginId
   *   The plugin_id for the widget.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $thirdPartySettings
   *   Any third party settings.
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The container.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockPluginManager
   *   The block plugin manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   */
  public function __construct(string $pluginId, mixed $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings, ContainerInterface $container, protected BlockManagerInterface $blockPluginManager, protected AccountProxyInterface $currentUser) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings, $container);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $pluginId,
      $pluginDefinition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container,
      $container->get('plugin.manager.block'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'media_type_id' => NULL,
      'library_label' => NULL,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $formState): array {
    $form = parent::settingsForm($form, $formState);

    $mediaTypeIds = $this->getMediaTypeStorage()->getQuery()
      ->accessCheck(FALSE)
      ->condition('source', 'image')
      ->condition('source_configuration.source_field', 'image_library_widget_image')
      ->execute();

    if (!$mediaTypeIds) {
      if ($this->currentUser->hasPermission('administer media types')) {
        $message = $this->t('There are no media types configured to be used as image libraries. Until you <a href=":url">add some media types</a>, configured to act as image libraries, the widget will act as normal image widget.', [
          ':url' => Url::fromRoute('entity.media_type.add_form')->toString(),
        ]);
      }
      else {
        $message = $this->t('There are no media types configured to be used as image libraries. Ask the site administrator to add some media types, configured to act as image libraries. Until then the widget will act as normal image widget.');
      }
      $form['warning'] = [
        '#theme' => 'status_messages',
        '#message_list' => ['warning' => [$message]],
      ];
      $form['media_type_id'] = [
        '#type' => 'value',
        '#value' => NULL,
      ];
    }
    else {
      $options = [];
      foreach ($this->getMediaTypeStorage()->loadMultiple($mediaTypeIds) as $mediaTypeId => $mediaType) {
        $options[$mediaTypeId] = $mediaType->label();
      }

      $form['media_type_id'] = [
        '#type' => 'select',
        '#title' => $this->t('Image library type'),
        '#options' => $options,
        '#default_value' => $this->getSetting('media_type_id'),
        '#required' => TRUE,
      ];
      $form['library_label'] = [
        '#type' => 'textfield',
        '#title' => $this->t('A label to be displayed above the library browser'),
        '#description' => $this->t('Use the @type placeholder to indicate the type of library.'),
        '#default_value' => $this->getSetting('library_label'),
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();

    if ($this->fieldDefinition->getFieldStorageDefinition()->getCardinality() !== 1) {
      $summary[] = $this->t("<strong>Warning!</strong> %label (<code>@name</code>) is a multi-value field. The <em>Image Library Widget</em> doesn't support yet fields with a cardinality other than 1. Support multi-value image fields to be added in <a href='https://www.drupal.org/project/image_library_widget/issues/3103574'>#3103574</a>.", [
        '%label' => $this->fieldDefinition->getLabel(),
        '@name' => $this->fieldDefinition->getName(),
      ]);
    }
    elseif ($this->getSetting('media_type_id')) {
      $summary[] = $this->t('Image library type: @media_type', [
        '@media_type' => $this->getMediaType()->label(),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $formState): array {
    $element = parent::formElement($items, $delta, $element, $form, $formState);
    // Get the allowed file extensions from the field's settings.
    $fieldSettings = $items->getFieldDefinition()->getSettings();
    $element['#upload_validators']['FileExtension'] = ['extensions' => $fieldSettings['file_extensions']];

    $fieldName = $element['#field_name'];
    $cardinality = $this->fieldDefinition->getFieldStorageDefinition()->getCardinality();

    // Only image fields with a single value (0..1) are currently supported.
    // @todo Support multi-value image fields in #3103574.
    // @see https://www.drupal.org/project/image_library_widget/issues/3103574
    if ($cardinality !== 1) {
      throw new \Exception("Image Library Widget multi-value fields is not supported yet.");
    }

    // Only show when we're on the item that renders the upload button.
    if (!$items[$delta]->isEmpty() || !empty($formState->getValue($fieldName)[0])) {
      return $element;
    }

    // We don't add the image library browser as a child of the element because
    // if we do so, it would be rendered before the field's description. But the
    // field description is meaningful only for the managed file widget itself.
    // Instead, we'll append it in the form_element__image_library_widget theme,
    // just after the description.
    // @see templates/form-element--image-library-widget.html.twig
    $element['#image_library_widget'] = [];

    $cacheMetadata = new CacheableMetadata();

    // Vary on permissions as some users might not be allowed to access the
    // image library widget browser.
    $cacheMetadata->addCacheContexts(['user.permissions']);

    /** @var \Drupal\Core\Entity\Display\EntityFormDisplayInterface $formDisplay */
    $formDisplay = $formState->getStorage()['form_display'];
    // Invalidate the cache when the widget settings are updated.
    $cacheMetadata->addCacheableDependency($formDisplay);

    if ($mediaTypeId = $this->getSetting('media_type_id')) {
      // @todo Use a configurable view and display in #3104544.
      // @see https://www.drupal.org/project/image_library_widget/issues/3104544
      /** @var \Drupal\views\Plugin\Block\ViewsBlock $blockPlugin */
      $blockPlugin = $this->blockPluginManager->createInstance("views_block:image_library_widget-block");
      if ($blockPlugin->access($this->currentUser)) {
        $blockPlugin->setContextValue('bundle', $mediaTypeId);
        $blockBuild = $blockPlugin->build();

        // If the view display has been configured with a pager, it should use
        // Ajax, as navigating to a different widget page should be done without
        // reloading the page where the widget is rendered.
        /** @var \Drupal\views\ViewExecutable $view */
        if ($view =& $blockBuild['#view']) {
          if (!in_array($view->getPager()->getPluginId(), ['none', 'some']) && !$view->ajaxEnabled()) {
            throw new \Exception('When the image library widget is configured to use a pager, the view should be Ajax enabled.');
          }
        }

        // Prepare the browser label.
        $label = trim((string) $this->getSetting('library_label'));
        $label = $label && isset($view) ? str_replace('@type', $this->getMediaType()->label(), $label) : NULL;

        $element['#image_library_widget'] = [
          '#theme' => 'image_library_widget_browser',
          '#label' => $label,
          '#browser' => $blockBuild,
          '#has_results' => isset($view),
          '#upload_validators' => [
            'FileExtension' => [
              'extensions' => $fieldSettings['file_extensions'],
            ],
          ],
          '#attributes' => [
            'data-drupal-field' => $fieldName,
            'class' => [
              'image-library-widget',
              'js-image-library-widget',
            ],
          ],
          '#attached' => [
            'library' => [
              'image_library_widget/browser',
            ],
          ],
        ];
      }
    }

    $cacheMetadata->applyTo($element['#image_library_widget']);

    // Use a custom form render element just to be able to apply our own theme
    // template as we don't want to render the image library browser together
    // with the managed file element but we have to place it after the form
    // element.
    $element['#type'] = 'image_library_widget';

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies(): array {
    $dependencies = parent::calculateDependencies();
    if ($mediaTypeId = $this->getSetting('media_type_id')) {
      $mediaType = $this->getMediaTypeStorage()->load($mediaTypeId);
      // The widget depends on the media type ID.
      $dependencies[$mediaType->getConfigDependencyKey()][] = $mediaType->getConfigDependencyName();
    }
    return $dependencies;
  }

  /**
   * {@inheritdoc}
   */
  public function onDependencyRemoval(array $dependencies): bool {
    $changed = parent::onDependencyRemoval($dependencies);
    $mediaTypeId = $this->getSetting('media_type_id');
    if ($mediaTypeId && $mediaType = $this->getMediaTypeStorage()->load($mediaTypeId)) {
      if (!empty($dependencies[$mediaType->getConfigDependencyKey()][$mediaType->getConfigDependencyName()])) {
        // Don't hide the widget if the corresponding media type is deleted.
        $this->setSetting('media_type_id', NULL);
        $changed = TRUE;
      }
    }
    return $changed;
  }

  /**
   * Returns the media type config entity for this widget.
   *
   * @return \Drupal\media\MediaTypeInterface|null
   *   The media type of this widget.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the 'media_type' storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the 'media_type' entity type doesn't exist.
   */
  protected function getMediaType(): ?MediaTypeInterface {
    if ($mediaTypeId = $this->getSetting('media_type_id')) {
      /** @var \Drupal\media\MediaTypeInterface $mediaType */
      $mediaType = $this->getMediaTypeStorage()->load($mediaTypeId);
      return $mediaType;
    }
    return NULL;
  }

  /**
   * Returns the media type entity storage.
   *
   * @return \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   *   The media type entity storage.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the 'media' storage handler couldn't be loaded.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the 'media' entity type doesn't exist.
   */
  protected function getMediaTypeStorage(): ConfigEntityStorageInterface {
    if (!isset($this->mediaTypeStorage)) {
      $this->mediaTypeStorage = $this->entityTypeManager->getStorage('media_type');
    }
    return $this->mediaTypeStorage;
  }

}
