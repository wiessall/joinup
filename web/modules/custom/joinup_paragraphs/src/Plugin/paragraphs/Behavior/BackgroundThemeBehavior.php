<?php

declare(strict_types = 1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a behavior for paragraphs to set a background theme.
 *
 * @ParagraphsBehavior(
 *   id = "background_theme",
 *   label = @Translation("Background theme"),
 *   description = @Translation("Provides a behavior for paragraphs to set a background theme."),
 * )
 */
class BackgroundThemeBehavior extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $theme = $paragraph->getBehaviorSetting($this->getPluginId(), 'background_theme');
    $build['#attributes']['class'][] = 'background-theme-' . $theme;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $form['background_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Background theme'),
      '#options' => [
        'light' => $this->t('Light'),
        'light-wave' => $this->t('Light wave'),
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'background_theme'),
    ];
  }

}
