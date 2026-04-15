<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Form;

use Drupal\Core\Entity\BundleEntityFormBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user_achievement\Entity\AchievementType;

/**
 * Form handler for achievement type add and edit forms.
 */
final class AchievementTypeForm extends BundleEntityFormBase {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\user_achievement\Entity\AchievementTypeInterface $achievement_type */
    $achievement_type = $this->entity;

    if ($this->operation === 'edit') {
      $form['#title'] = $this->t('Edit %label achievement type', ['%label' => $achievement_type->label()]);
    }

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $achievement_type->label(),
      '#description' => $this->t('The human-readable name of this achievement type (e.g. "Active Participation").'),
      '#required' => TRUE,
    ];

    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $achievement_type->id(),
      '#maxlength' => EntityTypeInterface::BUNDLE_MAX_LENGTH,
      '#machine_name' => [
        'exists' => [AchievementType::class, 'load'],
        'source' => ['label'],
      ],
      '#description' => $this->t('A unique machine-readable name. It must only contain lowercase letters, numbers, and underscores.'),
    ];

    $form['description'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#default_value' => $achievement_type->getDescription(),
      '#description' => $this->t('Explanatory text shown as a tooltip on the public user profile (e.g. "This user has contributed as an active user on the Portal.").'),
      '#required' => TRUE,
      '#rows' => 3,
    ];

    $icons = AchievementType::getAvailableIcons();
    $form['icon'] = [
      '#type' => 'select',
      '#title' => $this->t('Icon'),
      '#options' => array_combine($icons, $icons),
      '#default_value' => $achievement_type->getIcon(),
      '#empty_option' => $this->t('- Select an icon -'),
      '#required' => TRUE,
      '#description' => $this->t('Choose a Ventuno theme icon to represent this badge. Icon names correspond to Bootstrap Icons in the theme sprite.'),
    ];

    return $this->protectBundleIdElement($form);
  }

  /**
   * {@inheritdoc}
   */
  protected function actions(array $form, FormStateInterface $form_state): array {
    $actions = parent::actions($form, $form_state);
    $actions['submit']['#value'] = $this->t('Save achievement type');
    $actions['delete']['#value'] = $this->t('Delete achievement type');
    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);

    $messageArgs = ['%label' => $this->entity->label()];
    $this->messenger()->addStatus(
      match($result) {
        SAVED_NEW => $this->t('The achievement type %label has been added.', $messageArgs),
        SAVED_UPDATED => $this->t('The achievement type %label has been updated.', $messageArgs),
      }
    );
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));

    return $result;
  }

}
