<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies regular user.
 *
 * @todo Drop this language negotiator in ISAICP-10215.
 */
#[LanguageNegotiation(
  id: self::METHOD_ID,
  name: new TranslatableMarkup('Force English for regular users'),
  types: [LanguageInterface::TYPE_INTERFACE],
  weight: -10,
  description: new TranslatableMarkup('Enforced English UI for regular users.')
)]
class LanguageNegotiationRegularUser extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method id.
   */
  const string METHOD_ID = 'joinup_force_english';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL): ?string {
    $isPrivilegedUser = $this->currentUser->hasPermission('bypass enforced english');
    if ($isPrivilegedUser) {
      return NULL;
    }

    return 'en';
  }

}
