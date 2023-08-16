<?php

declare(strict_types = 1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a behavior for paragraphs color.
 *
 * @ParagraphsBehavior(
 *   id = "color",
 *   label = @Translation("Content color"),
 *   description = @Translation("Provides a behavior for content color."),
 * )
 */
class ColorBehavior extends ParagraphsBehaviorBase {

  /**
   * Default alignment.
   */
  const DEFAULT_COLOR = 'primary';

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->themeManager = $container->get('theme.manager');

    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['#paragraph_color'] = $paragraph->getBehaviorSetting($this->getPluginId(), 'color', self::DEFAULT_COLOR);
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



