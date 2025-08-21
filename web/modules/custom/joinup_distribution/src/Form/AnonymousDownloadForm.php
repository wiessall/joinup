<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\EventSubscriber\AjaxResponseSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\FileInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Retrieves the e-mail from anonymous users that are downloading distributions.
 */
class AnonymousDownloadForm extends FormBase {

  use AutowireTrait;

  /**
   * The file being downloaded.
   *
   * @var \Drupal\file\FileInterface
   */
  protected FileInterface $file;

  public function __construct(
    #[Autowire(service: 'database')]
    protected readonly Connection $database,
    #[Autowire(service: 'entity_type.manager')]
    protected $entityTypeManager,
    #[Autowire(service: 'file.usage')]
    protected readonly FileUsageInterface $fileUsage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'anonymous_download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?FileInterface $file = NULL): array {
    $this->file = $file;

    $form['#id'] = Html::getId($this->getFormId());

    $form['notice'] = [
      '#markup' => $this->t('If you do not have a Interoperable Europe Portal account, please take some time to create one, at <a href=":register">this page</a>. It will allow you to fully exploit the functionalities of Interoperable Europe Portal to create new content, contribute to existing one and collaborate with other users.<br />If you do not want to create a Interoperable Europe Portal account, please select any of the following options and provide your email address if you want to be kept updated on the status of the solution. Your personal data will be treated in compliance with the <a href=":legal">legal notice</a>', [
        ':register' => Url::fromRoute('user.register')->toString(),
        ':legal' => Url::fromRoute('entity.entity_legal_document.canonical', ['entity_legal_document' => 'legal_notice'])->toString(),
      ]),
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail address'),
      '#description' => $this->t('Your e-mail address as <em>john.doe@domain.com</em>'),
      '#maxlength' => 180,
      '#required' => TRUE,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('No thanks') ,
      // This button will be used only to close the modal, so it doesn't have
      // any submit callback.
      '#submit' => [],
      '#attributes' => [
        'class' => ['dialog-cancel'],
      ],
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        // Enable ajax submit for the whole form by specifying the wrapper.
        // Since the submit callback returns an ajax response, there is no need
        // to provide an ajax callback. The wrapper will be used in case of
        // validation errors.
        'wrapper' => $form['#id'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Prevent any non-Javascript submits.
    if (!$this->getRequest()->request->get(AjaxResponseSubscriber::AJAX_REQUEST_PARAMETER)) {
      throw new BadRequestHttpException();
    }

    $usages = $this->fileUsage->listUsage($this->file);
    if (!empty($usages['file']['paragraph'])) {
      if (count($usages['file']['paragraph']) > 1) {
        throw new \RuntimeException('More than one paragraphs were found for the file with ID ' . $this->file->id());
      }

      // Get the node that uses the paragraph. Distributions use the
      // 'resources' field.
      $paragraphId = key($usages['file']['paragraph']);
      $query = $this->database->select('node__resources', 'n');
      $query->fields('n', ['entity_id']);
      $query->condition('n.resources_target_id', $paragraphId);
      $result = $query->execute()->fetchField();

      if (!$result) {
        throw new \RuntimeException('No node was found using the paragraph with ID ' . $paragraphId);
      }

      // Add the file usage for the node and mock the usage count to 1.
      $usages['file']['node'][$result] = 1;
    }

    // Normally, only one distribution is allowed to use a file and only
    // distributions call this code.
    if (empty($usages['file']['node'])) {
      throw new \RuntimeException('No distributions were found using the file with ID ' . $this->file->id());
    }
    if (count($usages['file']['node']) > 1) {
      throw new \RuntimeException('More than one distributions were found for the file with ID ' . $this->file->id());
    }
    $distribution = $this->entityTypeManager->getStorage('node')->load(key($usages['file']['node']));
    if (empty($distribution)) {
      throw new \RuntimeException('No distribution was found with the ID ' . key($usages['file']['node']));
    }
    if (!$distribution instanceof DistributionInterface) {
      throw new \RuntimeException('The entity with ID ' . $distribution->id() . ' is not a distribution.');
    }

    /** @var \Drupal\joinup_solution\Entity\SolutionInterface|\Drupal\joinup_release\Entity\ReleaseInterface $parent */
    $parent = $distribution->getParent();
    $event = $this->entityTypeManager->getStorage('download_event')->create([
      'uid' => 0,
      'mail' => $form_state->getValue('email'),
      'file' => $this->file->id(),
      'parent_entity_type' => $parent->getEntityTypeId(),
      'parent_entity_id' => $parent->id(),
    ]);
    $event->save();

    $response = new AjaxResponse();
    $response->addCommand(new CloseModalDialogCommand());
    $form_state->setResponse($response);
  }

}
