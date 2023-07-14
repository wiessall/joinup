<?php

declare(strict_types = 1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a behavior for paragraphs to set custom classes.
 *
 * @ParagraphsBehavior(
 *   id = "extra_class",
 *   label = @Translation("Extra classes"),
 *   description = @Translation("Extra classes to be passed on the layout level."),
 * )
 */
class ClassesBehavior extends ParagraphsBehaviorBase {

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode) {
    $extraClasses = $paragraph->getBehaviorSetting($this->getPluginId(), 'extra_class');
    if ($extraClasses === NULL || empty(trim($extraClasses))) {
      return;
    }
    $build['#attributes']['class'] = array_merge($build['#attributes']['class'] ?? [], explode(' ', $extraClasses));
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state) {
    $form['extra_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Extra classes'),
      '#description' => $this->t('Extra classes to be passed on the layout level. Split multiple classes by space.'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'extra_class'),
    ];
  }

}
