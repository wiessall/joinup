<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\paragraphs\Behavior;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsBehaviorBase;

/**
 * Provides a paragraphs behavior plugin for ToC.
 *
 * @ParagraphsBehavior(
 *   id = "table_of_content",
 *   label = @Translation("Table of Content"),
 *   description = @Translation("Configurable table of content"),
 * )
 */
class TableOfContentsBehavior extends ParagraphsBehaviorBase {

  /**
   * Default max heading level.
   */
  const string DEFAULT_MAX_LEVEL = 'h5';

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(ParagraphsType $paragraphs_type): bool {
    return $paragraphs_type->id() === 'table_of_contents';
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

  /**
   * {@inheritdoc}
   */
  public function view(array &$build, Paragraph $paragraph, EntityViewDisplayInterface $display, $view_mode): void {
    $data = $this->generateFlatListOfLinks($paragraph);
    $data = $this->addMissingLevels($data);
    $data = $this->generateNestedListOfLinks($data);

    $build['list'] = $this->renderedItems($data);
    $build['list']['#title'] = $paragraph->get('paragraph_title')->getString();
  }

  /**
   * Generates flat list of links.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $toc
   *   ToC paragraph.
   *
   * @return array
   *   Links.
   */
  protected function generateFlatListOfLinks(ParagraphInterface $toc): array {
    $max_level =
      $this->getLevel(
        $toc->getBehaviorSetting($this->getPluginId(), 'max_level', self::DEFAULT_MAX_LEVEL)
      );

    if (!isset($toc->_referringItem)) {
      return [];
    }
    $field_item_list = $toc->_referringItem->getParent();
    if (!$field_item_list instanceof EntityReferenceFieldItemListInterface) {
      return [];
    }

    $paragraphs = $field_item_list->getEntity()
      ->get($field_item_list->getName())
      ->referencedEntities();
    $headings = array_filter($paragraphs, function ($paragraph) use ($max_level) {
      $level = $this->getLevel(
        $paragraph->getBehaviorSetting('heading_behavior', 'level', self::DEFAULT_MAX_LEVEL)
      );

      return $paragraph->bundle() === 'heading' && $level <= $max_level;
    });

    return array_map(function ($paragraph) {
      $level = $this->getLevel(
        $paragraph->getBehaviorSetting('heading_behavior', 'level', self::DEFAULT_MAX_LEVEL)
      );

      return [
        'level' => $level - 1,
        'name' => Link::fromTextAndUrl(
          $paragraph->get('paragraph_heading')->getString(),
          Url::fromRoute('<none>', [], ['fragment' => 'paragraph-' . $paragraph->id()])
        )->toRenderable(),
      ];
    }, $headings);
  }

  /**
   * Adds missing levels to the dataset.
   *
   * If there are gaps in the levels, this function fills them with placeholders
   * (name = NULL).
   *
   * @param array $data
   *   Flat data with 'name' and 'level' keys.
   *
   * @return array
   *   The dataset with missing levels filled.
   */
  protected function addMissingLevels(array $data): array {
    $filled_data = [];
    $max_level = 0;

    foreach ($data as $item) {
      $current_level = $item['level'];

      while (++$max_level < $current_level) {
        $filled_data[] = [
          'name' => NULL,
          'level' => $max_level,
        ];
      }

      $filled_data[] = $item;
      $max_level = $current_level;
    }

    return $filled_data;
  }

  /**
   * Generates nested list of links.
   *
   * @param array $data
   *   Flat list.
   *
   * @return array
   *   Nested list.
   */
  protected function generateNestedListOfLinks(array $data): array {
    $nested = [];
    $parents = [&$nested];

    foreach ($data as $item) {
      $level = $item['level'];

      $parents = array_slice($parents, 0, $level);

      $current = [
        'heading' => $item['name'],
        'children' => [],
      ];
      $parents[$level - 1][] = $current;

      $parents[$level] = &$parents[$level - 1][count($parents[$level - 1]) - 1]['children'];
    }

    return $nested;
  }

  /**
   * Recursively build a render array for a nested item_list.
   *
   * @param array $items
   *   The nested array of items with 'name' and 'children' keys.
   *
   * @return array
   *   A render array for the nested list.
   */
  protected function renderedItems(array $items): array {
    $rendered_items = [];

    foreach ($items as $item) {
      $list_item = [];
      if (isset($item['heading'])) {
        $list_item['heading'] = $item['heading'];
      }
      if (!empty($item['children'])) {
        $list_item['children'] = $this->renderedItems($item['children']);
      }

      $rendered_items[] = $list_item;
    }

    return [
      '#theme' => 'item_list',
      '#items' => $rendered_items,
      '#list_type' => 'ul',
    ];
  }

  /**
   * Transforms level to number.
   *
   * @param string $level
   *   Level setting.
   *
   * @return int
   *   Level.
   */
  protected function getLevel(string $level): int {
    return (int) str_replace('h', '', $level);
  }

}
