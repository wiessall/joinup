<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Form\UnshareForm as OriginalForm;
use Drupal\node\NodeInterface;

/**
 * Form to unshare a solution from within collections.
 *
 * The methods are different from the parent class because the route is a sub
 * link of the `/rdf_entity/{rdf_entity}` route path. That means that we cannot
 * have the `{rdf_entity}` parameter named differently and also, even if the
 * rdf entity is implementing the EntityInterface, the ArgumentResolver would
 * not automatically assign it to another entity of the same type.
 *
 * @todo Is this needed anymore?
 */
class UnshareForm extends OriginalForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    return parent::doBuildForm($form, $form_state, $node);
  }

  /**
   * Access callback for the unshare form.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(NodeInterface $node): AccessResultInterface {
    $this->entity = $node;
    return AccessResult::allowedIf(!empty($this->getGroups()));
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle(EntityInterface $node): TranslatableMarkup {
    return $this->t('Unshare %title from', ['%title' => $node->label()]);
  }

}
