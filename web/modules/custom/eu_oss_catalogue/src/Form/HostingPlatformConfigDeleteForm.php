<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete form for 'hosting_platform' config entities.
 */
class HostingPlatformConfigDeleteForm extends EntityDeleteForm {

  /**
   * Constructs a hosting platform config delete form.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   Entity field manager.
   */
  public function __construct(
    protected EntityFieldManagerInterface $entityFieldManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $usage = $this->countPluginUsage();
    if ($usage) {
      $caption = $this->formatPlural(
        $usage,
        '%label is used by 1 node on your site. You can not remove this provider until you have removed all of the %label nodes.',
        '%label is used by @count nodes on your site. You may not remove %label until you have removed all of the %label nodes.',
        ['%label' => $this->entity->getEntityTypeId() . ':' . $this->entity->id()]
      );
      $form['description'] = ['#markup' => '<p>' . $caption . '</p>'];

      return $form;
    }
    else {
      return parent::buildForm($form, $form_state);
    }
  }

  /**
   * Counts plugin usage.
   *
   * @return int
   *   Number of usage.
   */
  protected function countPluginUsage(): int {
    $fields = $this->getFieldMapForNodes();
    if (empty($fields)) {
      return 0;
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE);

    $orGroup = $query->orConditionGroup();
    foreach ($fields as $fieldName => $data) {
      $andGroup = $query->andConditionGroup();
      $andGroup->condition($fieldName, $this->entity->getEntityTypeId() . ':' . $this->entity->id());
      $andGroup->condition('type', $data['bundles'], 'IN');

      $orGroup->condition($andGroup);
    }
    $query->condition($orGroup);

    return $query->count()
      ->execute();
  }

  /**
   * Gets field map for nodes.
   *
   * @return array
   *   An array keyed by field name.
   */
  protected function getFieldMapForNodes(): array {
    $fields = $this->entityFieldManager->getFieldMapByFieldType('plugin_reference');
    return $fields['node'] ?? [];
  }

}
