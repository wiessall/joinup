<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_test\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsComponent;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;

/**
 * Reusable methods for paragraphs.
 */
trait ParagraphTrait {

  /**
   * Creates a paragraph and marks it for cleanup.
   *
   * @param array $values
   *   The values to use for the paragraph.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The created paragraph.
   */
  public function createParagraph(array $values = []): ParagraphInterface {
    if (!isset($values['type'])) {
      $values += [
        'type' => 'simple_paragraph',
      ];
    }
    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph */
    $paragraph = \Drupal::entityTypeManager()->getStorage('paragraph')->create($values);
    $paragraph->save();

    if (method_exists($this, 'markEntityForCleanup')) {
      $this->markEntityForCleanup($paragraph);
    }
    else {
      $this->cleanupEntities[] = $paragraph;
    }
    return $paragraph;
  }

  /**
   * Creates a paragraph with the given values.
   *
   * If saved, then the paragraph is queued to be removed after the actual test
   * is finished.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to add the paragraph to. Since we strictly follow the
   *   logic of Layout Paragraphs, the entity must be saved already.
   * @param string $paragraphFieldName
   *   The field name of the paragraph reference field on the entity.
   * @param array $values
   *   The values of the paragraph; including its type.
   * @param string $layoutId
   *   The ID of the layout to use. Defaults to 'layout_onecol'.
   * @param string $regionId
   *   The layout region to add the paragraph to. Defaults to 'content'.
   */
  protected function appendParagraphToEntityLayout(
    ContentEntityInterface $entity,
    string $paragraphFieldName,
    array $values,
    string $layoutId = 'layout_onecol',
    string $regionId = 'content',
  ): void {
    $this->assertNotEmpty($layoutId);
    $this->assertNotEmpty($regionId);
    $type = ParagraphsType::load($values['type']);
    $this->assertInstanceOf(ParagraphsTypeInterface::class, $type);
    $values += ['status' => TRUE];
    $itemParagraph = Paragraph::create($values);
    $layoutParagraph = $this->createLayoutParagraph($layoutId);

    $paragraphsField = $entity->get($paragraphFieldName);
    $this->assertInstanceOf(EntityReferenceFieldItemListInterface::class, $paragraphsField);

    // Add the new layout paragraph to the entity.
    // @see \Drupal\layout_paragraphs\LayoutParagraphsLayout::appendComponent().
    $layoutParagraph->setParentEntity($entity, $paragraphFieldName);
    $paragraphsField->appendItem(['entity' => $layoutParagraph]);

    // Add the new paragraph to the entity.
    // @see \Drupal\layout_paragraphs\LayoutParagraphsLayout::insertIntoRegion().
    $component = new LayoutParagraphsComponent($itemParagraph);
    $component->setSettings([
      'parent_uuid' => $layoutParagraph->uuid(),
      'region' => $regionId,
    ]);
    $itemParagraph->setParentEntity($entity, $paragraphFieldName);
    $paragraphsField->appendItem(['entity' => $itemParagraph]);
    $this->cleanupEntities[] = $itemParagraph;
  }

  /**
   * Creates a layout paragraph with the given layout.
   *
   * @param string $layoutId
   *   The ID of the layout.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The not-yet-saved layout paragraph entity.
   */
  protected function createLayoutParagraph(string $layoutId): ParagraphInterface {
    $layoutDiscovery = \Drupal::service('plugin.manager.core.layout');
    $this->assertInstanceOf(LayoutPluginManagerInterface::class, $layoutDiscovery);
    // Fail loud if the layout is missing.
    $this->assertTrue($layoutDiscovery->hasDefinition($layoutId));
    $layoutInstance = $layoutDiscovery->createInstance($layoutId);

    // Create the layout paragraph and set its settings.
    $layoutParagraph = Paragraph::create(['type' => 'layout']);
    $layoutParagraph->setBehaviorSettings(
      'layout_paragraphs',
      [
        'layout' => $layoutInstance->getPluginId(),
        'config' => $layoutInstance->getConfiguration(),
        'parent_uuid' => NULL,
        'region' => NULL,
      ]
    );
    $this->cleanupEntities[] = $layoutParagraph;
    return $layoutParagraph;
  }

}
