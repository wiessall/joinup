<?php

declare(strict_types = 1);

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
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $alignment = $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment');

    $build['#attributes']['class'][] = 'd-flex';
    $build['#attributes']['class'][] = $this->getAlignmentClass($alignment);
  }

  /**
   * Gets alignment class.
   *
   * @param string|null $alignment
   *   Alignment value.
   *
   * @return string
   *   Alignment class
   */
  protected function getAlignmentClass(?string $alignment): string {
    return match ($alignment) {
      'center' => 'justify-content-center',
      'right' => 'justify-content-end',
      default => 'justify-content-start',
    };
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
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'alignment') ?? 'left',
    ];
  }

}
