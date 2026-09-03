<?php

declare(strict_types=1);

namespace Drupal\contact_form\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for contact form tokens.
 */
final class ContactFormTokenHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * Implements hook_token_info().
   */
  #[Hook('token_info')]
  public function tokenInfo(): array {
    $info['tokens']['site']['contact-form'] = [
      'name' => $this->t('The contact form of the site'),
      'description' => $this->t('The absolute url of the contact form of the site.'),
    ];

    return $info;
  }

  /**
   * Implements hook_tokens().
   */
  #[Hook('tokens')]
  public function tokens(string $type, array $tokens, array $data, array $options, BubbleableMetadata $bubbleableMetadata): array {
    $replacements = [];
    if ($type === 'site' && !empty($tokens['contact-form'])) {
      $replacements[$tokens['contact-form']] = $this->urlGenerator->generateFromRoute('contact_form.contact_page', [], ['absolute' => TRUE]);
    }
    return $replacements;
  }

}
