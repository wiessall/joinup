<?php

declare(strict_types=1);

namespace Drupal\custom_page\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Plugin\Block\GroupMenuBlock;

/**
 * Table-Of-Contents for top-level custom pages.
 */
#[Block(
  id: "custom_page_toc:navigation",
  admin_label: new TranslatableMarkup("Custom page TOC"),
  category: new TranslatableMarkup("Custom page"),
  context_definitions: [
    'og' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Organic group"),
    ),
  ],
)]
class TableOfContents extends GroupMenuBlock {

  /**
   * {@inheritdoc}
   */
  protected function getCurrentRouteMenuTreeParameters(): MenuTreeParameters {
    $parameters = new MenuTreeParameters();

    $this->setMinDepth($parameters)->setMaxDepth($parameters);

    // Get the topmost/root custom page in this hierarchy.
    $trail = $this->menuActiveTrail->getActiveTrailIds($this->getMenuName());
    // Remove the empty root element.
    array_pop($trail);
    // Grab the root page menu link.
    $root_link = array_pop($trail);

    // This block shows only links under the root custom page tree.
    return $parameters->setRoot($root_link)->onlyEnabledLinks();
  }

  /**
   * {@inheritdoc}
   */
  protected function getEmptyResultsBuild(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function addContextualLinks(array &$build): void {}

}
