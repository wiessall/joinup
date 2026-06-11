<?php

declare(strict_types=1);

namespace Drupal\joinup_i18n\Plugin\LanguageNegotiation;

use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\language\Attribute\LanguageNegotiation;
use Drupal\language\LanguageNegotiationMethodBase;
use Joinup\TestModeManager;
use Symfony\Component\HttpFoundation\Request;

/**
 * Forces English for users without access to the user-preferred language.
 *
 * @todo Remove once historic content is fully translated. See ISAICP-11330.
 */
#[LanguageNegotiation(
  id: self::METHOD_ID,
  name: new TranslatableMarkup('All pages always in English'),
  types: [LanguageInterface::TYPE_INTERFACE, LanguageInterface::TYPE_CONTENT],
  weight: -10,
  description: new TranslatableMarkup('Enforces English on all pages for users without the "view admin pages in preferred language" permission.')
)]
class LanguageNegotiationAdminEnglish extends LanguageNegotiationMethodBase {

  /**
   * The language negotiation method id.
   */
  const string METHOD_ID = 'joinup_admin_english';

  /**
   * {@inheritdoc}
   */
  public function getLangcode(?Request $request = NULL): ?string {
    $langcode = 'en';

    if (
      $this->currentUser->hasPermission('view admin pages in preferred language') ||
      TestModeManager::isActive()
    ) {
      $langcode = NULL;
    }
    return $langcode;
  }

}
