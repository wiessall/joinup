<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a paragraphs behaviour plugin for heading.
 *
 * @ParagraphsBehavior(
 *   id = "heading_behavior",
 *   label = @Translation("Heading"),
 *   description = @Translation("Configurable heading"),
 * )
 */
class HeadingBehavior extends ParagraphsBehaviorBase {

  /**
   * Default heading level.
   */
  const DEFAULT_LEVEL = 'h2';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphsType): bool {
    return $paragraphsType->id() === 'heading';
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $level = $paragraph->getBehaviorSetting($this->getPluginId(), 'level', self::DEFAULT_LEVEL);

    $build['paragraph_heading'][0] = [
      '#theme' => 'joinup_paragraphs_heading',
      '#level' => $level,
      '#text' => $paragraph->get('paragraph_heading')->getString(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): array {
    $form['level'] = [
      '#type' => 'select',
      '#title' => $this->t('Heading level'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'level', self::DEFAULT_LEVEL),
      '#options' => [
        'h2' => $this->t('H2 heading'),
        'h3' => $this->t('H3 heading'),
        'h4' => $this->t('H4 heading'),
        'h5' => $this->t('H5 heading'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(Paragraph $paragraph): array {
    $level = $paragraph->getBehaviorSetting($this->getPluginId(), 'level', self::DEFAULT_LEVEL);

    return [$this->t('Heading level : @level', ['@level' => $level])];
  }

}
