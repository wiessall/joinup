<?php

/**
 * @file
 * Tokens support for Contact information module.
 */

declare(strict_types = 1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\joinup_group\Entity\GroupInterface;

/**
 * Implements hook_token_info().
 */
function contact_information_token_info(): array {
  return [
    'tokens' => [
      'email-confirmer' => [
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
    if ($name === 'confirmation-entities') {
      $gids = $confirmation->getProperty('groups');
      $groups = \Drupal::entityTypeManager()->getStorage('rdf_entity')->loadMultiple($gids);
      $render_array = [
        '#theme' => 'item_list',
        '#items' => array_values(
          array_map(
            function (GroupInterface $group): array {
              return [
                '#type' => 'link',
                '#url' => $group->toUrl()->setAbsolute(),
                '#title' => $group->label(),
                '#prefix' => "{$group->get('rid')->entity->label()}: ",
              ];
            },
            $groups)
        ),
      ];
      $replacements[$original] = \Drupal::service('renderer')->renderPlain($render_array);
      break;
    }
  }

  return $replacements;
}
