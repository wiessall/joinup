<?php

declare(strict_types = 1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a behavior for paragraphs pill.
 *
 * @ParagraphsBehavior(
 *   id = "pill",
 *   label = @Translation("Pill"),
 *   description = @Translation("Provides a behavior for content pill."),
 * )
 */
class PillBehavior extends ParagraphsBehaviorBase {

  /**
   * Default pill.
   */
  const DEFAULT_PILL = FALSE;

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['#joinup_paragraph_pill'] = $paragraph->getBehaviorSetting($this->getPluginId(), 'pill', self::DEFAULT_PILL);
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $form['pill'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pill'),
      '#description' => $this->t('Make element more rounded.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'pill', self::DEFAULT_PILL),
    ];
  }

}
