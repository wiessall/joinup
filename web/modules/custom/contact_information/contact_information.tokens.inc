<?php

/**
 * @file
 * Tokens support for Contact information module.
 */

declare(strict_types = 1);

use Drupal\Core\Render\BubbleableMetadata;

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
        $replacements[$original] = $confirmation->getProperty('entities');
        break;
    }
  }

  return $replacements;
}
