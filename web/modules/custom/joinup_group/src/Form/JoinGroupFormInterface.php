<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Interface for forms that allow users to join groups.
 */
interface JoinGroupFormInterface extends FormInterface {

  /**
   * Returns the label for the join submit button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the label.
   */
  public function getJoinSubmitLabel(): TranslatableMarkup;

  /**
   * Returns the label for the leave submit button.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title of the label.
   */
  public function getLeaveSubmitLabel(): TranslatableMarkup;

  /**
   * Returns the group to join.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The group entity.
   */
  public function getGroup(): GroupInterface;

}
