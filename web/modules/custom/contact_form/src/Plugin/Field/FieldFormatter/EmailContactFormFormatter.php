<?php

declare(strict_types = 1);

namespace Drupal\contact_form\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Plugin implementation of the 'email_contact_form' formatter.
 *
 * @FieldFormatter(
 *   id = "email_contact_form",
 *   label = @Translation("Email contact form popup"),
 *   field_types = {
 *     "email",
 *   },
 *   settings = {
 *     "default_message" = "[current-user:name] sent a message using the
 *     contact form at [current-page:url]."
 *   }
 * )
 */
class EmailContactFormFormatter extends FormatterBase {

  /**
   * Module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * Constructs an EmailAction object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\contact_form\Plugin\Field\FieldFormatter\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ModuleHandlerInterface $module_handler, LoggerChannelFactoryInterface $loggerFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->moduleHandler = $module_handler;
    $this->logger = $loggerFactory->get('contact_form');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition,
      $container->get('module_handler'),
      $container->get('logger.factory')->get('action'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $element = [];

    $element['include_values'] = [
      '#title' => $this->t('Display all field values in email body'),
      '#type' => 'checkbox',
      '#default_value' => $this->getSetting('include_values'),
    ];

    $element['default_message'] = [
      '#title' => $this->t('Additional message in email body'),
      '#type' => 'textfield',
      '#default_value' => $this->getSetting('default_message'),
    ];

    if ($this->moduleHandler->moduleExists('token')) {
      $element['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['node'],
      ];
    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'include_values' => 1,
      'default_message' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = [];
    $summary[] = $this->t('Displays a contact form popup for this email.');
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      /** @var \Drupal\Core\Entity\EntityInterface $entity */
      $entity = $item->getEntity();
      try {
        $elements[$delta]['open_modal'] = [
          '#type' => 'link',
          '#title' => $this->t('Contact owner'),
          '#url' => Url::fromRoute('contact_form.open_modal_contact_form', [
            'entity_type' => $entity->getEntityTypeId(),
            'entity' => $entity->id(),
            'field_name' => $items->getName(),
          ]),
          '#attributes' => [
            'class' => [
              'use-ajax',
            ],
          ],
        ];
      }
      catch (NotFoundHttpException $e) {
        $this->logger->notice('Invalid inline contact form on @entity_type id @id.', [
          '@entity_type' => $entity->getEntityTypeId(),
          '@id' => $entity->id(),
        ]);
      }
      break;
    }

    // Attach the library for pop-up dialogs/modals.
    $elements['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $elements;
  }

}
