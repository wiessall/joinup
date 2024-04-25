<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\LanguageNegotiation;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * Language negotiation handler for OSS Solutions.
 *
 * Allows users with appropriate permissions to view and edit content
 * translations for OSS solutions, without affecting the entire website.
 *
 * @LanguageNegotiation(
 *   id = Drupal\joinup_oss_catalogue\Plugin\LanguageNegotiation\OssContentNegotiation::METHOD_ID,
 *   types = {Drupal\Core\Language\LanguageInterface::TYPE_CONTENT},
 *   weight = -9,
 *   name = @Translation("OSS Content language"),
 *   description = @Translation("Custom language negotiation handler for OSS Solutions."),
 * )
 */
class OssContentNegotiation extends LanguageNegotiationContentEntity {

  /**
   * The language negotiation method ID.
   */
  const METHOD_ID = 'oss-language-content-entity';

  /**
   * The query string parameter.
   */
  const QUERY_PARAMETER = 'oss_language_content_entity';

  /**
   * {@inheritdoc}
   */
  public function processOutbound($path, &$options = [], ?Request $request = NULL, ?BubbleableMetadata $bubbleable_metadata = NULL): string {
    // We only process URLs that have been explicitly set with the option.
    /* @see \Drupal\eu_oss_catalogue\Entity\OssSolution::toUrl */
    if (isset($options[self::METHOD_ID]) && $options[self::METHOD_ID] === TRUE) {
      $path = parent::processOutbound($path, $options, $request, $bubbleable_metadata);
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url) {
    // OSS Solutions are storing translations, but we do not want to expose the
    // corresponding pages in the frontend, only the default translation.
    return [];
  }

}
