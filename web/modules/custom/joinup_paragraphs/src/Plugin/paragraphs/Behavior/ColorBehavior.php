<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\paragraphs\Attribute\ParagraphsBehavior;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a behavior for paragraphs color.
 */
#[ParagraphsBehavior(
  id: "color",
  label: new TranslatableMarkup("Content color"),
  description: new TranslatableMarkup("Provides a behavior for content color."),
)]
class ColorBehavior extends ParagraphsBehaviorBase {

  /**
   * Default alignment.
   */
  private const DEFAULT_COLOR = 'primary';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    EntityFieldManagerInterface $entityFieldManager,
    protected readonly ThemeManagerInterface $themeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entityFieldManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_field.manager'),
      $container->get('theme.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['#joinup_paragraph_color'] = $paragraph->getBehaviorSetting($this->getPluginId(), 'color', self::DEFAULT_COLOR);
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $options = [
      'primary' => $this->t('Primary'),
      'brand' => $this->t('Brand'),
      'secondary' => $this->t('Secondary'),
    ];

    $this->themeManager->alter('paragraphs_behavior_color_options', $options);

    $form['color'] = [
      '#type' => 'select',
      '#title' => $this->t('Color'),
      '#options' => $options,
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'color', self::DEFAULT_COLOR),
    ];
  }

}
