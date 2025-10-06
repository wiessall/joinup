<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldFormatter;

use Drupal\assessments\Plugin\Field\FieldWidget\AssessmentProviderWidget;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Locale\CountryManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'Assessment Provider' formatter.
 *
 * @FieldFormatter(
 *   id = "assessment_provider",
 *   label = @Translation("Assessment Provider"),
 *   field_types = {"assessment_provider"},
 * )
 */
final class AssessmentProviderFormatter extends FormatterBase {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, private readonly CountryManager $countryManager) {
    parent::__construct($plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('country_manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings(): array {
    return parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];

    // Map machine type to human label using the widget options for consistency.
    $typeOptions = AssessmentProviderWidget::getTypeOptions();
    $countryList = $this->countryManager->getList();

    foreach ($items as $delta => $item) {
      $parts = [];
      $meta = [];

      $value = (string) ($item->value ?? '');
      $type = (string) ($item->type ?? '');
      $country = (string) ($item->country ?? '');

      if ($value !== '') {
        $parts[] = $value;
      }

      if ($type !== '') {
        // Translate type machine name to label if available.
        $label = $typeOptions[$type] ?? $type;
        $meta[] = $label;
      }

      // Country shown only for local/regional/national public sector bodies.
      if ($country !== '' && in_array($type, [
          'local_public_sector_body',
          'regional_public_sector_body',
          'national_public_sector_body',
        ], TRUE)) {
        $meta[] = $countryList[$country] ?? $country;
      }

      if ($meta) {
        $parts[] = '(' . implode(', ', $meta) . ')';
      }

      $elements[$delta] = [
        '#markup' => implode(' ', $parts),
      ];
    }

    return $elements;
  }

}
