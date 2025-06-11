<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUserAdmin;
use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies admin language from the user preferences.
 */
#[LanguageNegotiation(
  id: self::METHOD_ID,
  name: new TranslatableMarkup('Admin pages always in English'),
  types: [LanguageInterface::TYPE_INTERFACE],
  weight: -10,
  description: new TranslatableMarkup('Enforces English as the default language on all administrative pages for privileged users.')
)]
class LanguageNegotiationAdminEnglish extends LanguageNegotiationUserAdmin {

  /**
   * The language negotiation method id.
   */
  const string METHOD_ID = 'joinup_admin_english';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL): ?string {
    if (!$request) {
      return NULL;
    }

    $isAdminUser = $this->currentUser->hasPermission('view admin pages in preferred language');
    if (!$isAdminUser && $this->isAdminPath($request)) {
      return 'en';
    }

    return NULL;
  }

}
