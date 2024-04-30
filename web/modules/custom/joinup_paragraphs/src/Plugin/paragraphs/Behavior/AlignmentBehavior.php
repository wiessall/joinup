<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a behavior for paragraphs alignment.
 *
 * @ParagraphsBehavior(
 *   id = "alignment",
 *   label = @Translation("Content alignment"),
 *   description = @Translation("Provides a behavior for content alignment."),
 * )
 */
class AlignmentBehavior extends ParagraphsBehaviorBase {

  /**
   * Default alignment.
   */
  const DEFAULT_ALIGNMENT = 'left';

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['#joinup_paragraph_alignment'] = $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment', self::DEFAULT_ALIGNMENT);
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $form['alignment'] = [
      '#type' => 'select',
      '#title' => $this->t('Alignment'),
      '#options' => [
        'left' => $this->t('Left'),
        'center' => $this->t('Center'),
        'right' => $this->t('Right'),
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment', self::DEFAULT_ALIGNMENT),
    ];
  }

}
