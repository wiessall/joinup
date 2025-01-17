<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_newsroom_newsletter\Form\NewsletterSubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Field formatter which displays a subscription form.
 *
 * @FieldFormatter(
 *   id = "oe_newsroom_newsletter_subscribe_form",
 *   label = @Translation("Subscribe form"),
 *   field_types = {
 *     "oe_newsroom_newsletter"
 *   }
 * )
 */
class NewsletterSubscribeFormFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  public function __construct(
    string $plugin_id,
    mixed $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    string $label,
    string $view_mode,
    array $third_party_settings,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);
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
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    // @todo Support multivalue fields.
    if ($items->count() > 1) {
      throw new \Exception('Multivalue support is not implemented yet for the newsletter field formatter.');
    }

    if ($items->isEmpty()) {
      return [];
    }

    /** @var \Drupal\oe_newsroom_newsletter\Plugin\Field\FieldType\NewsletterItemInterface $item */
    $item = $items->first();

    if (!$item->isEnabled()) {
      return [];
    }

    return $this->formBuilder->getForm(NewsletterSubscribeForm::class, $item->getUniverse(), $item->getServiceId());
  }

}
