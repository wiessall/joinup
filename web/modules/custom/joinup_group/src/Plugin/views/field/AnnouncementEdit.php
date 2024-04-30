<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\field;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Url;
use Drupal\joinup_group\Entity\AnnouncementInterface;
use Drupal\views\Plugin\views\field\EntityLink;
use Drupal\views\ResultRow;

/**
 * Field handler to present a group announcement edit link.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("joinup_group_announcement_edit_link")
 */
class AnnouncementEdit extends EntityLink {

  /**
   * {@inheritdoc}
   */
  protected function getUrlInfo(ResultRow $row): Url {
    $announcement = $this->getEntity($row);
    if (!$announcement instanceof AnnouncementInterface) {
      throw new \LogicException("The '{$this->getPluginId()}' Views field plugin can be used only with 'message' entity type and 'announcement' bundle.");
    }
    return Url::fromRoute('entity.rdf_entity.announcement_edit', [
      'rdf_entity' => $announcement->getGroupId(),
      'announcement' => $announcement->id(),
    ])->setAbsolute($this->options['absolute']);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultLabel(): MarkupInterface {
    return $this->t('edit');
  }

}
