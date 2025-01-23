<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a paragraphs behaviour plugin for ToC.
 *
 * @ParagraphsBehavior(
 *   id = "table_of_content_behavior",
 *   label = @Translation("Table of Content"),
 *   description = @Translation("Configurable table of content"),
 * )
 */
class TableOfContentsBehaviour extends ParagraphsBehaviorBase {

  /**
   * Default max heading level.
   */
  const string DEFAULT_MAX_LEVEL = 'h4';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type): bool {
    return $paragraphs_type->id() === 'table_of_contents';
  }

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $build['list'] = [
      '#title' => $this->t('Tasks'),
      '#theme' => 'item_list',
      '#items' => $this->generateItems($paragraph),
    ];
    dpm($build['list']);
  }

  protected function generateItems(ParagraphInterface $paragraph): array {
    $max_level =
      $this->getLevel(
        $paragraph->getBehaviorSetting($this->getPluginId(), 'max_level', self::DEFAULT_MAX_LEVEL)
      );

    $headings = $this->getHeadingParagraphs($paragraph, $max_level);
    $entity = $paragraph->_referringItem?->getParent()?->getEntity();

    $last_level = 2;
    $items = [];
    foreach ($headings as $headings_paragraph) {
      $heading_level = $this->getLevel(
        $paragraph->getBehaviorSetting('heading_behavior', 'level', self::DEFAULT_MAX_LEVEL)
      );

      $url = $entity->toUrl()
        ->setOption('fragment', 'paragraph-' . $headings_paragraph->id());
      if ($last_level == $heading_level) {
        $items[] = Link::fromTextAndUrl($headings_paragraph->get('paragraph_heading')
          ->getString(), $url);
        $items[] = [
          '#theme' => 'item_list',
          '#items' => $items,
        ];
      }

      $last_level = $heading_level;
    }

    return $items;
  }

  /**
   * Gets heading paragraphs.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   ToC paragraph.
   *
   * @return \Drupal\paragraphs\ParagraphInterface[]
   *   Heading paragraphs.
   */
  protected function getHeadingParagraphs(ParagraphInterface $paragraph, int $max_level): array {
    if (!isset($paragraph->_referringItem)) {
      return [];
    }
    $field_item_list = $paragraph->_referringItem?->getParent();
    if (!$field_item_list instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    $field_name = $field_item_list->getName();
    $entity = $field_item_list->getEntity();

    return array_filter($entity->get($field_name)
      ->referencedEntities(), function($paragraph) use ($max_level) {
      $heading_level = $this->getLevel(
        $paragraph->getBehaviorSetting('heading_behavior', 'level', self::DEFAULT_MAX_LEVEL)
      );

      return $paragraph->bundle() === 'heading' && $max_level >= $heading_level;
    });
  }

  protected function getLevel(string $level): int {
    return (int) str_replace('h', '', $level);
  }

  /**
   * {@inheritdoc}
   */
  public function buildBehaviorForm(ParagraphInterface $paragraph, array &$form, FormStateInterface $form_state): array {
    $form['max_level'] = [
      '#type' => 'select',
      '#title' => $this->t('Max heading level'),
      '#default_value' => $paragraph->getBehaviorSetting($this->getPluginId(), 'max_level', self::DEFAULT_MAX_LEVEL),
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
    $level = $paragraph->getBehaviorSetting($this->getPluginId(), 'max_level', self::DEFAULT_MAX_LEVEL);

    return [$this->t('Max heading level : @level', ['@level' => $level])];
  }

}
