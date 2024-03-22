<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field\FieldFormatter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceLabelFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display the label with tooltip of the referenced entities.
 *
 * @FieldFormatter(
 *   id = "joinup_entity_reference_label_tooltip",
 *   label = @Translation("Label + tooltip"),
 *   description = @Translation("Display the label with tooltip of the referenced entities."),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class EntityReferenceLabelTooltip extends EntityReferenceLabelFormatter {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token|null
   */
  protected ?Token $token;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->token = $container->get('token', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return [
      'tooltip_text' => '',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    $elements = parent::settingsForm($form, $form_state);
    $elements['tooltip_text'] = [
      '#title' => $this->t('The tooltip text to display'),
      '#type' => 'textfield',
      '#required' => TRUE,
      '#default_value' => $this->getSetting('tooltip_text'),
    ];

    if ($this->token) {
      $elements['tooltip_text']['#description'] = $this->t('Supports tokens e.g. [term:description].');

      $type = $this->fieldDefinition->getFieldStorageDefinition()->getSetting('target_type');
      // @todo see https://drupal.org/node/2163027.
      $type = $type === 'taxonomy_term' ? 'term' : $type;
      $elements['token_help'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => [$type],
      ];
    }
    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Tooltip text: %tooltip_text.', [
      '%tooltip_text' => $this->getSetting('tooltip_text'),
    ]);
    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);

    foreach ($elements as $delta => $element) {
      $entity = $element['#entity'] ?? NULL;
      if (!$entity instanceof EntityInterface) {
        continue;
      }

      $tooltipText = $this->getTooltipText($entity);
      if (isset($element['#type']) && $element['#type'] === 'link') {
        $elements[$delta]['#options']['title'] = $tooltipText;
      }
      elseif (isset($element['#plain_text'])) {
        $elements[$delta]['#theme_wrappers']['container'] = ['#attributes' => ['title' => $tooltipText]];
      }
    }

    return $elements;
  }

  /**
   * Returns the tooltip text for the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity.
   *
   * @return string
   *   The tooltip text.
   */
  protected function getTooltipText(EntityInterface $entity): string {
    $tooltip_text = $this->getSetting('tooltip_text');
    if ($this->token) {
      $tooltip_text = $this->token->replace($tooltip_text, [$entity->getEntityTypeId() => $entity]);
    }
    return $tooltip_text;
  }

}
