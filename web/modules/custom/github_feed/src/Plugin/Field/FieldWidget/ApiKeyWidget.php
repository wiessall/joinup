<?php

namespace Drupal\github_feed\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'github_feed_api_key' field widget.
 *
 * @FieldWidget(
 *   id = "github_feed_api_key",
 *   label = @Translation("Github API authentication"),
 *   field_types = {"reference"},
 * )
 */
class ApiKeyWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ApiKeyWidget object.
   *
   * @param $pluginId
   *   The plugin ID for the widget.
   * @param $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $fieldDefinition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $thirdPartySettings
   *   Any third party settings.
   * @param \Drupal\key\KeyRepositoryInterface $keyRepository
   *   The key repository service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct($pluginId, $pluginDefinition, FieldDefinitionInterface $fieldDefinition, array $settings, array $thirdPartySettings, protected KeyRepositoryInterface $keyRepository, protected EntityTypeManagerInterface $entityTypeManager, protected FileSystemInterface $fileSystem) {
    parent::__construct($pluginId, $pluginDefinition, $fieldDefinition, $settings, $thirdPartySettings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('key.repository'),
      $container->get('entity_type.manager'),
      $container->get('file_system')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return parent::defaultSettings() + [
        'key_provider' => '../keys',
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    // Allow the user to select the filepath to store the keys.
    $form['key_provider'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key provider'),
      '#description' => $this->t('The key provider to use to store the GitHub API key. Can be an absolute/relative path to Drupal root, or a Drupal stream wrapper URI.'),
    ];

    return $form;
  }

  public function settingsSummary() {
    return parent::settingsSummary() + [
        $this->t('Key provider: @provider', ['@provider' => $this->getSetting('key_provider')]),
      ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $formState) {
    // The field widget will store the following:
    // - The GitHub username.
    // - The GitHub personal access token.
    // - The reference to the key provider (possibly file).
    $element['username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitHub username'),
      '#default_value' => isset($items[$delta]->username) ? $items[$delta]->username : NULL,
      '#required' => TRUE,
    ];

    $element['token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('GitHub personal access token'),
      // If there is a default value, provide only a placeholder calling the
      // user to not alter the value if they don't want to change it.
      '#placeholder' => isset($items[$delta]->token) ? $this->t('Do not change this value unless you want to change the token.') : NULL,
      '#required' => TRUE,
    ];

    // Offer a checkbox to allow the user to delete the key.
    $element['delete_key'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Delete the key'),
      '#description' => $this->t('Check this box to delete the key on save.'),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $formState) {
    $values = parent::massageFormValues($values, $form, $formState);
    // Get the username and the password as a json string.
    $keyValue = json_encode([
      'username' => $values[0]['username'],
      'token' => $values[0]['token'],
    ]);

    // Generate a key ID based on the username and the parent entity ID.
    $keyId = 'github_feed_' . $values[0]['username'] . '_' . $formState->getFormObject()->getEntity()->id();

    $directory = $this->getSetting('key_provider');
    // If the directory is a stream wrapper URI, convert it to a local path.
    if (strpos($directory, '://') !== FALSE) {
      $directory = $this->fileSystem->realpath($directory);
    }
    // Create the directory and file if it does not exist.
    $this->fileSystem->createFilename($keyId, $directory);

    // Check if the key exists.
    $key = $this->keyRepository->getKey($keyId);
    // If the key does not exist, create it.
    if (!$key) {
      // Create the key.
      $key = $this->entityTypeManager->getStorage('key')->create([
        'id' => $keyId,
        'label' => $keyId,
        'key_type' => 'authentication',
        'key_provider' => $this->getSetting('key_provider'),
        'key_settings' => [
          'file_location' => $this->getSetting('key_provider') . '/' . $keyId . '.json',
        ],
        'key_input' => $keyValue,
      ]);
      $key->save();
    }
    // If the key exists, update it.
    else {
      $key->set('key_input', $keyValue);
      $key->save();
    }

    $values['target_id'] = $key;
    return $values;
  }

}
