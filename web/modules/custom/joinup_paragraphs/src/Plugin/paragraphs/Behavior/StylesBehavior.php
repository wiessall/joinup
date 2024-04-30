<?php

declare(strict_types=1);

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
 *   id = "styles",
 *   label = @Translation("Styles"),
 *   description = @Translation("Provides a styles behavior."),
 * )
 */
class StylesBehavior extends ParagraphsBehaviorBase {

  /**
   * Default pill.
   */
  const DEFAULT_STYLES = [];

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['#joinup_paragraph_styles'] = $paragraph->getBehaviorSetting($this->getPluginId(), 'styles', self::DEFAULT_STYLES);
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): void {
    $form['styles'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Additional styles'),
      '#options' => [
        'rounded' => $this->t('Rounded corners'),
      ],
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'styles', self::DEFAULT_STYLES),
    ];
  }

}
