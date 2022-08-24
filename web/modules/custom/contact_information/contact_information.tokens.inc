<?php

/**
 * @file
 * Tokens support for Contact information module.
 */

declare(strict_types = 1);

use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Implements hook_token_info().
 */
function contact_information_token_info(): array {
  return [
    'tokens' => [
      'email-confirmer' => [
        'confirmation-name' => [
          'name' => t('Confirmation Name'),
          'description' => t('The name to confirm.'),
        ],
        'confirmation-entities' => [
          'name' => t('Contact information entities list'),
          'description' => t('Generates a list of entities with this contact information.'),
        ],
      ],
    ],
  ];
}

/**
 * Implements hook_tokens().
 */
function contact_information_tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata): array {
  if ($type != 'email-confirmer' || !isset($data['email_confirmer_confirmation'])) {
    return [];
  }

  $replacements = [];

  /** @var \Drupal\email_confirmer\EmailConfirmationInterface $confirmation */
  $confirmation = $data['email_confirmer_confirmation'];

  foreach ($tokens as $name => $original) {
    switch ($name) {
      case 'confirmation-name':
        $replacements[$original] = $confirmation->getProperty('name');
        break;

      case 'confirmation-entities':
        $entities = $confirmation->getProperty('entities') ?? [];
        if (
          $confirmation->getProperty('fake_entity') &&
          ($group = \Drupal::routeMatch()->getParameter('rdf_entity')) &&
          $group instanceof GroupInterface
        ) {
          $bundle_info = \Drupal::getContainer()->get('entity_type.bundle.info')->getBundleInfo($group->getEntityTypeId())[$group->bundle()];
          $entities[] = ['#markup' => $bundle_info['label'] . ': ' . Link::fromTextAndUrl($group->label(), $group->toUrl('canonical', ['absolute' => TRUE]))->toString()];
        }

        $elements = [
          '#theme' => 'item_list',
          '#items' => $entities,
        ];
        $replacements[$original] = \Drupal::service('renderer')->renderPlain($elements);
        break;
    }
  }

  return $replacements;
}
