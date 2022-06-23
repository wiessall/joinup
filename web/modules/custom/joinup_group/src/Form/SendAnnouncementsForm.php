<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityViewBuilderInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\message\Entity\Message;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\Og;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleManagerInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorage;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base form class for share/unshare entity forms.
 */
class SendAnnouncementsForm extends FormBase {

  /**
   * The current user account.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * The OG membership manager.
   *
   * @var \Drupal\og\MembershipManagerInterface
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * The OG role manager service.
   *
   * @var \Drupal\og\OgRoleManagerInterface
   */
  protected OgRoleManagerInterface $roleManager;

  /**
   * The entity being shared.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  /**
   * The RDF view builder.
   *
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected EntityViewBuilderInterface $rdfBuilder;

  /**
   * The RDF entity storage.
   *
   * @var \Drupal\sparql_entity_storage\SparqlEntityStorage
   */
  protected SparqlEntityStorage $sparqlStorage;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfoInterface
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  /**
   * Constructs a new ShareContentFormBase object.
   *
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorage $sparql_storage
   *   The RDF entity storage.
   * @param \Drupal\Core\Entity\EntityViewBuilderInterface $rdf_builder
   *   The RDF view builder.
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager.
   * @param \Drupal\og\OgRoleManagerInterface $role_manager
   *   The OG role manager service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user account.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfoInterface $bundle_info
   *   The bundle info service.
   */
  public function __construct(SparqlEntityStorage $sparql_storage, EntityViewBuilderInterface $rdf_builder, MembershipManagerInterface $membership_manager, OgRoleManagerInterface $role_manager, AccountInterface $current_user, MessengerInterface $messenger, EntityTypeBundleInfoInterface $bundle_info) {
    $this->sparqlStorage = $sparql_storage;
    $this->rdfBuilder = $rdf_builder;
    $this->membershipManager = $membership_manager;
    $this->roleManager = $role_manager;
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager')->getStorage('rdf_entity'),
      $container->get('entity_type.manager')->getViewBuilder('rdf_entity'),
      $container->get('og.membership_manager'),
      $container->get('og.role_manager'),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('entity_type.bundle.info')
    );
  }

  public function getFormId(): string {
    return 'joinup_announcement_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['subject'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subject'),
      '#required' => TRUE,
    ];

    $form['body'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Body'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    if (\Drupal::moduleHandler()->moduleExists('token')) {
      $form['tokens'] = [
        '#theme' => 'token_tree_link',
        '#token_types' => ['rdf_entity'],
        '#global_types' => TRUE,
        '#dialog' => TRUE,
      ];
    }

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $message = Message::create(['template' => 'announcement']);
    $message->save();
  }

}
