<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the 'assessment_provider' field widget.
 *
 * @FieldWidget(
 *   id = "assessment_provider",
 *   label = @Translation("Assessment Provider"),
 *   field_types = {"assessment_provider"},
 * )
 */
final class AssessmentProviderWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    protected readonly CountryManager $countryManager
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $third_party_settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('country_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state): array {
    $item = $items[$delta] ?? NULL;

    $element['assessment_type'] = [
      '#name' => 'assessment_type',
      '#type' => 'select',
      '#title' => $this->t('Type of public sector body'),
      '#options' => self::getTypeOptions(),
      '#default_value' => $item?->assessment_type ?? NULL,
      '#required' => TRUE,
      '#empty_option' => $this->t('- Select -'),
    ];

    $element['country'] = [
      '#type' => 'select',
      '#title' => $this->t('Country'),
      '#options' => $this->countryManager->getList(),
      '#default_value' => $item?->country ?? NULL,
      '#required' => FALSE,
      '#empty_option' => $this->t('- Select -'),
      '#states' => [
        'visible' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => [
            ['value' => 'local_public_sector_body'],
            'or',
            ['value' => 'regional_public_sector_body'],
            'or',
            ['value' => 'national_public_sector_body'],
          ],
        ],
        'required' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => [
            ['value' => 'local_public_sector_body'],
            'or',
            ['value' => 'regional_public_sector_body'],
            'or',
            ['value' => 'national_public_sector_body'],
          ],
        ],
      ],
    ];

    $element['value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Text value'),
      '#default_value' => $item?->value ?? NULL,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => [
            ['value' => 'local_public_sector_body'],
            'or',
            ['value' => 'regional_public_sector_body'],
            'or',
            ['value' => 'national_public_sector_body'],
          ],
        ],
        'required' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => [
            ['value' => 'local_public_sector_body'],
            'or',
            ['value' => 'regional_public_sector_body'],
            'or',
            ['value' => 'national_public_sector_body'],
          ],
        ],
      ],
    ];

    // Set 3 different select options for EU institutions, agencies, and bodies.
    $element['value_institution'] = [
      '#type' => 'select',
      '#title' => $this->t('European Union institution'),
      '#options' => self::getEuropeanUnionInstitutions(),
      '#default_value' => $item?->value ?? NULL,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_institution'],
        ],
        'required' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_institution'],
        ],
      ],
    ];

    $element['value_agency'] = [
      '#type' => 'select',
      '#title' => $this->t('European Union agency'),
      '#options' => [
        t('European Environment Agency'),
        t('European Medicines Agency'),
        t('European Food Safety Authority'),
      ],
      '#default_value' => $item?->value ?? NULL,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_agency'],
        ],
        'required' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_agency'],
        ],
      ],
    ];

    $element['value_body'] = [
      '#type' => 'select',
      '#title' => $this->t('European Union body'),
      '#options' => [
        t('European Central Bank'),
        t('European Court of Auditors'),
        t('European Investment Bank'),
      ],
      '#default_value' => $item?->value ?? NULL,
      '#required' => FALSE,
      '#states' => [
        'visible' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_body'],
        ],
        'required' => [
          ':input[name="' . $element['assessment_type']['#name'] . '"]' => ['value' => 'eu_body'],
        ],
      ],
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      // Remove empty country if not applicable.
      if (!in_array($value['assessment_type'], ['local_public_sector_body', 'regional_public_sector_body', 'national_public_sector_body'], TRUE)) {
        $value['country'] = NULL;
      }

      // Set the correct 'value' based on the selected type.
      switch ($value['assessment_type']) {
        case 'eu_institution':
          $value['value'] = $value['value_institution'];
          break;
        case 'eu_agency':
          $value['value'] = $value['value_agency'];
          break;
        case 'eu_body':
          $value['value'] = $value['value_body'];
          break;
        default:
          // For other types, 'value' is already set correctly.
          break;
      }

      $value['type'] = $value['assessment_type'];

      // Remove the temporary fields used for EU types.
      unset($value['assessment_type'], $value['value_institution'], $value['value_agency'], $value['value_body']);
    }

    return $values;
  }

  /**
   * Returns the options for the 'type' select element.
   *
   * @return array
   *   An associative array of type options.
   */
  public static function getTypeOptions(): array {
    return [
      'local_public_sector_body' => t('Local public sector body'),
      'regional_public_sector_body' => t('Regional public sector body'),
      'national_public_sector_body' => t('National public sector body'),
      'eu_institution' => t('European Union institution'),
      'eu_agency' => t('European Union agency'),
      'eu_body' => t('European Union body'),
    ];
  }

  /**
   * Returns a list of European Union institutions.
   *
   * @return array
   *   An array of European Union institution names.
   */
  public static function getEuropeanUnionInstitutions(): array {
    return [
      t('European Parliament'),
      t('Council of the European Union'),
      t('European Commission'),
    ];
  }

}
