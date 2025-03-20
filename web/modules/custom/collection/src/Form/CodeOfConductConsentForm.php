<?php

declare(strict_types=1);

namespace Drupal\collection\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\collection\Entity\CodeOfConductMembershipInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\node\NodeInterface;

/**
 * Form for accepting new Code of Conduct document.
 */
class CodeOfConductConsentForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'collection_code_of_conduct_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $collection = $this->getRouteMatch()->getParameter('node');
    assert($collection instanceof CollectionInterface);
    $codeOfConduct = $collection->getCodeOfConduct();
    assert($codeOfConduct instanceof NodeInterface);

    $args = [
      '@label' => $collection->label(),
      ':url' => $codeOfConduct->toUrl()->toString(),
    ];
    $form['safe'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'To ensure safe and respectful environment for all members of @label, we are introducing a <a href=":url" target="_blank">Code of Conduct</a>.', $args
      ),
    ];

    $form['maintain'] = [
      '#type' => 'item',
      '#markup' => $this->t(
        'If you wish to maintain your membership in @label, please confirm below:', $args
      ),
    ];

    $form['accept'] = [
      '#type' => 'checkbox',
      '#required' => TRUE,
      '#title' => $this->t('I have read and agree to the <a href=":url" target="_blank">Code of Conduct</a> of @label.', $args),
      '#required_error' => $this->t('You must accept the <a href=":url" target="_blank">Code of Conduct</a>', $args),
    ];

    $form['actions'] = [
      '#type' => 'container',
    ];

    $form['actions']['accept'] = [
      '#value' => $this->t('Accept'),
      '#type' => 'submit',
    ];

    $form['actions']['revoke'] = [
      '#type' => 'link',
      '#title' => $this->t('Revoke membership'),
      '#url' => Url::fromRoute('joinup_group.leave_confirm_form', ['node' => $collection->id()]),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $collection = $this->getRouteMatch()->getParameter('node');
    assert($collection instanceof CollectionInterface);

    $codeOfConduct = $collection->getCodeOfConduct();
    assert($codeOfConduct instanceof NodeInterface);

    $membership = $collection->getMembership((int) $this->currentUser()->id());
    assert($membership instanceof CodeOfConductMembershipInterface);

    $membership
      ->grantFullMembershipWithCocAcceptance($codeOfConduct)
      ->save();
    $this->messenger()
      ->addStatus($this->t('Thank you for confirming your agreement to the Code of Conduct.'));
    $form_state->setRedirect('entity.node.canonical', ['node' => $collection->id()]);
  }

  /**
   * Access callback for the form.
   *
   * @param \Drupal\collection\Entity\CollectionInterface $node
   *   The node entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(CollectionInterface $node, AccountInterface $account): AccessResultInterface {
    $membership = $node->getMembership($account->id());

    $access = AccessResult::allowedIf(
      $node->hasCodeOfConductToAccept() &&
      $membership instanceof CodeOfConductMembershipInterface &&
      !$membership->isLastDocumentRevisionAccepted())
      ->cachePerUser()
      ->addCacheContexts(['og_group_context'])
      ->addCacheTags(Cache::buildTags('og-group-content', $node->getCacheTags()));

    if ($membership) {
      $access->addCacheTags($membership->getCacheTags());
    }

    return $access;
  }

}
