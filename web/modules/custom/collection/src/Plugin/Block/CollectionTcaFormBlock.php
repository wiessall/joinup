<?php

declare(strict_types=1);

namespace Drupal\collection\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Form\CollectionTcaForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'Collection TCA Form' block.
 */
#[Block(
  id: "collection_tca_form_block",
  admin_label: new TranslatableMarkup("Collection TCA Form"),
  category: new TranslatableMarkup("Forms")
)]
class CollectionTcaFormBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResult {
    return AccessResult::allowedIfHasPermission($account, 'propose collection content');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $form = $this->formBuilder->getForm(CollectionTcaForm::class);

    return [
      'collection_tca_form' => $form,
    ];
  }

}
