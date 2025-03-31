<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\user\Plugin\LanguageNegotiation\LanguageNegotiationUserAdmin;
use Symfony\Component\HttpFoundation\Request;

/**
 * Identifies admin language from the user preferences.
 */
#[LanguageNegotiation(
  id: 'joinup_admin_english',
  name: new TranslatableMarkup('Admin pages always in English'),
  types: [LanguageInterface::TYPE_INTERFACE],
  weight: -10,
  description: new TranslatableMarkup('Enforces English as the default language on all administrative pages for privileged users.')
)]
class LanguageNegotiationAdminEnglish extends LanguageNegotiationUserAdmin {

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL): ?string {
    $isAdminUser = $this->currentUser->hasPermission('access administration pages') ||
      $this->currentUser->hasPermission('view the administration theme');

    if ($isAdminUser && $request && $this->isAdminPath($request)) {
      return 'en';
    }

    return NULL;
  }

}
