<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs\Plugin\OgGroupResolver;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\og\Attribute\OgGroupResolver;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;

/**
 * Resolves group from the layout paragraphs.
 */
#[OgGroupResolver(
  id: 'group_from_layout_paragraphs',
  label: new TranslatableMarkup('Group from Layout Paragraphs'),
  description: new TranslatableMarkup('Resolves group from the layout paragraphs.')
)]
class LayoutParagraphsRouteGroupResolver extends RouteGroupResolver {

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/layout-paragraphs-builder/{layout_paragraphs_layout}/choose-component' => 'layout_paragraphs_layout',
      '/layout-paragraphs-builder/{layout_paragraphs_layout}/insert/{paragraph_type_id}' => 'layout_paragraphs_layout',
      '/layout-paragraphs-builder/{layout_paragraphs_layout}/edit/{component_uuid}' => 'layout_paragraphs_layout',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if (($layout_paragraph_layout = $this->getContentEntity()) && $layout_paragraph_layout instanceof LayoutParagraphsLayout) {
      $entity = $layout_paragraph_layout->getEntity();

      if ($entity instanceof GroupContentInterface && $entity->hasGroup()) {
        $collection->addGroup($entity->getGroup(), ['route']);
        $this->stopPropagation();
      }
    }
  }

}
