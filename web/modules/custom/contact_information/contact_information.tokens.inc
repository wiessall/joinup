<?php

/**
 * @file
 * Tokens support for Contact information module.
 */

declare(strict_types=1);

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
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
      $replacements[$original] = \Drupal::service('renderer')->renderInIsolation($render_array);
      break;
    }
  }

  return $replacements;
}

/**
 * Implements hook_tokens_alter().
 */
function contact_information_tokens_alter(array &$replacements, array $context, BubbleableMetadata $bubbleable_metadata): void {
  if ($context['type'] === 'email-confirmer' && isset($context['data']['email_confirmer_confirmation'])) {
    if (isset($context['tokens']['confirmation-url'])) {
      /** @var \Drupal\email_confirmer\EmailConfirmationInterface $confirmation */
      $confirmation = $context['data']['email_confirmer_confirmation'];
      $url = Url::fromRoute('entity.email_confirmer_confirmation.response_form', [
        'email_confirmer_confirmation' => $confirmation->uuid(),
        'hash' => $confirmation->getHash(),
      ])
        // The base URL is derived by the Symfony request handler from the
        // global variables set by the web server, i.e. REQUEST_URI or similar.
        // Since Behat tests are run from the command line this request context
        // is not available and we need to set the base URL manually.
        // @todo Remove this workaround once this is fixed in core.
        // @see https://www.drupal.org/project/drupal/issues/2548095
        ->setOption('base_url', $GLOBALS['base_url'])
        ->setAbsolute()
        ->toString();
      $replacements[$context['tokens']['confirmation-url']] = $url;
    }
  }
}
