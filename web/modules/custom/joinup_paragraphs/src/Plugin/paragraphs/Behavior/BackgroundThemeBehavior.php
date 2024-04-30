<?php

declare(strict_types=1);

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
    $theme ? $build['#attributes']['class'][] = 'paragraph--layout--' . $theme : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $form['background_theme'] = [
      '#type' => 'select',
      '#title' => $this->t('Background theme'),
      '#options' => [
        '' => $this->t('None'),
        'light' => $this->t('Light grey'),
        'secondary' => $this->t('Light blue'),
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'background_theme'),
    ];
  }

}
