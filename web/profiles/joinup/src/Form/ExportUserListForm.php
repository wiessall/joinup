<?php

declare(strict_types=1);

namespace Drupal\joinup\Form;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\csv_serialization\Encoder\CsvEncoder;
use Drupal\joinup_user\EntityAuthorshipHelperInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Provides a form to export the list of users to CSV.
 */
class ExportUserListForm extends FormBase {

  /**
   * Defines the fields that will be exported, with their i/o processors.
   */
  protected const EXPORTED_FIELDS = [
    'Username' => [
      'input' => ['getFieldValues', ['name']],
      'output' => ['getFirstValue', 'formatString'],
    ],
    'First name' => [
      'input' => ['getFieldValues', ['field_user_first_name']],
      'output' => ['getFirstValue', 'formatString'],
    ],
    'Surname' => [
      'input' => ['getFieldValues', ['field_user_family_name']],
      'output' => ['getFirstValue', 'formatString'],
    ],
    'Email' => [
      'input' => ['getFieldValues', ['mail']],
      'output' => ['getFirstValue', 'formatString'],
    ],
    'EU Login name' => [
      'input' => ['getFieldValues', ['eulogin_authname']],
      'output' => ['getFirstValue', 'formatString'],
    ],
    'Status' => [
      'input' => ['getFieldValues', ['status']],
      'output' => ['getFirstValue', 'formatStatus'],
    ],
    'Roles' => [
      'input' => ['getFieldValues', ['roles']],
      'output' => ['formatRoles'],
    ],
    'Registration' => [
      'input' => ['getFieldValues', ['created']],
      'output' => ['getFirstValue', 'formatDate'],
    ],
    'Last access' => [
      'input' => ['getFieldValues', ['access']],
      'output' => ['getFirstValue', 'formatDate'],
    ],
    'Author' => [
      'input' => ['getIsAuthor', []],
      'output' => ['formatBoolean'],
    ],
    'Profile' => [
      'input' => ['getFieldValues', ['uid']],
      'output' => ['getFirstValue', 'formatProfileLink'],
    ],
    'Country of origin' => [
      'input' => ['getFieldValues', ['field_user_nationality']],
      'output' => ['formatTaxonomyTermLabel'],
    ],
    'Professional domain' => [
      'input' => ['getFieldValues', ['field_user_professional_domain']],
      'output' => ['formatTaxonomyTermLabel'],
    ],
    'Organisation' => [
      'input' => ['getFieldValues', ['field_user_organisation']],
      'output' => ['getAllValues', 'formatString'],
    ],
    'Business title' => [
      'input' => ['getFieldValues', ['field_user_business_title']],
      'output' => ['getFirstValue', 'formatString'],
    ],
  ];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityAuthorshipHelperInterface $entityAuthorshipHelper,
    protected MembershipManagerInterface $membershipManager,
    protected FileSystemInterface $fileSystem,
    protected CsrfTokenGenerator $csrfTokenGenerator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('joinup_user.entity_authorship_helper'),
      $container->get('og.membership_manager'),
      $container->get('file_system'),
      $container->get('csrf_token')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'joinup_export_user_list';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['filename'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Filename'),
      '#default_value' => 'userlist-' . date('Y-m-d') . '.csv',
      '#size' => 60,
      '#maxlength' => 128,
      '#required' => TRUE,
    ];

    $form['group'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('Group'),
      '#description' => $this->t('Filter on a specific collection / solution.'),
      '#maxlength' => 1024,
      '#target_type' => 'node',
      '#selection_settings' => [
        'target_bundles' => ['collection', 'solution'],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $group_id = $form_state->getValue('group');
    if ($group_id) {
      // Retrieve all user IDs for a specific group.
      $group_entity = $this->entityTypeManager->getStorage('node')->load($group_id);
      \assert($group_entity instanceof EntityInterface);
      $user_ids = $this->getGroupMemberUids($group_entity);
    }
    else {
      // Retrieve all user IDs, but exclude the anonymous user.
      $user_ids = $this->getUserStorage()->getQuery()->accessCheck(FALSE)->execute();
      unset($user_ids[0]);
    }

    // Split up the work in batches of 250 users.
    $form_class = $this;
    $batch_operations = array_map(function ($user_ids_batch) use ($form_class) {
      return [[$form_class, 'collectUserData'], [$user_ids_batch]];
    }, array_chunk($user_ids, 250));

    // After the batch process is finished we will redirect to a controller that
    // will serve the CSV file as a download. We need to pass the filename to
    // use in the download in the session.
    // @see batch_set()
    $_SESSION['export_filename'] = $form_state->getValue('filename');
    $batch = [
      'title' => 'Exporting user list',
      'init_message' => 'Starting collection of user data.',
      'operations' => $batch_operations,
      'finished' => [$this, 'redirectToDownload'],
    ];
    batch_set($batch);
  }

  /**
   * Batch command to collect data from user accounts to include in the report.
   *
   * @param string[] $user_ids
   *   A batch of user IDs to process.
   * @param array $context
   *   The batch context.
   */
  public function collectUserData(array $user_ids, array &$context): void {
    $results = [];
    foreach ($this->getUserStorage()->loadMultiple($user_ids) as $user) {
      foreach (self::EXPORTED_FIELDS as $id => $field) {
        // Get the data from the input processor.
        [$method, $arguments] = $field['input'];
        array_unshift($arguments, $user);
        $results[$user->id()][$id] = $this->$method(...$arguments);
      }
    }

    // Compile the results as a table.
    $headers = array_keys(self::EXPORTED_FIELDS);
    $rows = [];
    foreach ($results as $result) {
      $columns = [];
      foreach (self::EXPORTED_FIELDS as $id => $field) {
        // Run the data through the output processors.
        $output = $result[$id];
        foreach ($field['output'] as $method) {
          $output = $this->$method($output);
        }
        $columns[] = $output;
      }
      $rows[] = array_combine($headers, $columns);
    }

    // Convert the data into CSV format.
    $data = (new CsvEncoder())->encode($rows, 'csv');

    // Create a temporary file to store the result in, if it doesn't exist yet.
    if (empty($context['results'])) {
      $context['results'] = [
        $this->fileSystem->tempnam('temporary://', 'file'),
      ];
    }
    // If the file has been created in a previous batch this means the CSV
    // headers have already been output. Strip them from the result so they are
    // not duplicated in the file.
    else {
      $data = preg_replace('/^.+\n/', "\n", $data);
    }

    if (file_put_contents($context['results'][0], $data, FILE_APPEND) === FALSE) {
      $this->messenger()->addError($this->t('The CSV file could not be created.'));
      throw new HttpException(500);
    }
  }

  /**
   * Batch finished callback. Formats the user list as a CSV file.
   *
   * @param bool $success
   *   Whether the batch ended without a fatal error.
   * @param array $results
   *   The result set.
   * @param array $operations
   *   The remaining operations.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A redirect response to the page that serves the CSV file as a download.
   */
  public function redirectToDownload(bool $success, array $results, array $operations): Response {
    if (!$success) {
      $this->messenger()->addError('An error occurred during the export of the user data.');
      throw new HttpException(500);
    }

    // Redirect to a controller that will serve the CSV file as a download. We
    // need to pass the temporary file that contains the export in a session
    // variable. Also pass a CSRF token to protect against session tampering.
    // @see batch_set()
    $_SESSION['temp_filename'] = $results[0];
    $_SESSION['csrf_token'] = $this->csrfTokenGenerator->get($results[0]);
    return new RedirectResponse(Url::fromRoute('joinup.export_user_list_download')->toString());
  }

  /**
   * Input processor; returns the content of the given field on the user entity.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   * @param string $field_name
   *   The field name for which to return the content.
   *
   * @return array
   *   An array containing the field content.
   */
  protected function getFieldValues(UserInterface $user, string $field_name): array {
    $values = [];
    foreach ($user->get($field_name) as $field_item) {
      $values[] = $field_item->getValue();
    }
    return $values;
  }

  /**
   * Input processor; returns whether the user is an author.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   *
   * @return bool
   *   TRUE if the user has created any published community content or RDF
   *   content.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurred during the retrieval of the authorship
   *   information from Search API.
   */
  protected function getIsAuthor(UserInterface $user): bool {
    return !empty($this->entityAuthorshipHelper->getEntityIdsAuthoredByUser($user->id(), ['published']));
  }

  /**
   * Output processor; returns all field values of a multivalue array.
   *
   * @param array $value
   *   The multivalue array.
   *
   * @return mixed
   *   The first value.
   */
  protected function getAllValues(array $value): mixed {
    return array_filter(array_column($value, 'value'));
  }

  /**
   * Output processor; returns the first value of a multivalue array.
   *
   * @param array $value
   *   The multivalue array.
   *
   * @return mixed
   *   The first value.
   */
  protected function getFirstValue(array $value): mixed {
    if (($firstItem = reset($value)) === FALSE) {
      return NULL;
    }

    return array_key_exists('value', $firstItem)
      ? $firstItem['value']
      : $firstItem[key($firstItem)];
  }

  /**
   * Output processor; returns the passed in value as a simple string.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The value as a string.
   */
  protected function formatString($value): string {
    if (\is_array($value)) {
      return implode(PHP_EOL, $value);
    }

    return (string) $value;
  }

  /**
   * Output processor; returns the passed in value as a status string.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   Either 'active', or 'blocked'.
   */
  protected function formatStatus($value): string {
    return $value ? 'active' : 'blocked';
  }

  /**
   * Output processor; returns a comma separated list of roles.
   *
   * @param array $values
   *   The values.
   *
   * @return string
   *   A comma separated list of roles.
   */
  protected function formatRoles(array $values): string {
    $values = array_map(function (array $value) {
      return $value['target_id'];
    }, $values);
    sort($values);
    return implode(PHP_EOL, $values);
  }

  /**
   * Output processor; returns a formatted date.
   *
   * @param mixed $value
   *   The value.
   *
   * @return string
   *   The date, or 'N/A' if no value was passed.
   */
  protected function formatDate($value): string {
    if (!$value) {
      return 'N/A';
    }

    return date('Y-m-d h:i:s', (int) $value);
  }

  /**
   * Output processor; returns the passed in value as a boolean Yes/No.
   *
   * @param bool $value
   *   The value.
   *
   * @return string
   *   Either 'Yes', or 'No'.
   */
  protected function formatBoolean(bool $value): string {
    return $value ? 'Yes' : 'No';
  }

  /**
   * Output processor; returns a link to the user profile.
   *
   * @param bool $value
   *   The user ID.
   *
   * @return string
   *   An absolute URL to the user profile.
   */
  protected function formatProfileLink($value): string {
    return Url::fromRoute('entity.user.canonical', ['user' => (int) $value])->setAbsolute()->toString();
  }

  /**
   * Output processor; returns taxonomy term labels.
   *
   * @param array $values
   *   The field values.
   *
   * @return string
   *   The term labels.
   */
  protected function formatTaxonomyTermLabel(array $values): string {
    $ids = array_filter(array_column($values, 'target_id'));
    if (empty($ids)) {
      return '';
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $labels = array_map(static function (TermInterface $term): string {
      return $term->label();
    }, $storage->loadMultiple($ids));
    return implode(PHP_EOL, $labels);
  }

  /**
   * Returns the user storage.
   *
   * @return \Drupal\user\UserStorageInterface
   *   The user storage.
   */
  protected function getUserStorage(): UserStorageInterface {
    return $this->entityTypeManager->getStorage('user');
  }

  /**
   * Returns user IDs of all active members for the given OG group.
   *
   * @param \Drupal\Core\Entity\EntityInterface $group
   *   The group entity.
   *
   * @return array
   *   An array of user IDs.
   */
  public function getGroupMemberUids(EntityInterface $group): array {
    $og_membership_ids = $this->membershipManager->getGroupMembershipIdsByRoleNames($group, [OgRoleInterface::AUTHENTICATED]);
    if (empty($og_membership_ids)) {
      return [];
    }

    $memberships = $this->entityTypeManager->getStorage('og_membership')->loadMultiple($og_membership_ids);
    return array_values(array_map(static function (OgMembershipInterface $membership): int {
      return (int) $membership->getOwnerId();
    }, $memberships));
  }

}
