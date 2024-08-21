<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\LanguageNegotiation;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\language\Plugin\LanguageNegotiation\LanguageNegotiationContentEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Route;

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
    /* @see \Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution::toUrl */
    if (isset($options[self::METHOD_ID]) && $options[self::METHOD_ID] === TRUE) {
      $path = parent::processOutbound($path, $options, $request, $bubbleable_metadata);
    }

    return $path;
  }

  /**
   * {@inheritdoc}
   */
  public function getLanguageSwitchLinks(Request $request, $type, Url $url): array {
    // OSS Solutions are storing translations, but we do not want to expose the
    // corresponding pages in the frontend, only the default translation.
    /* @see \Drupal\joinup_oss_catalogue\Entity\JoinupOssSolution::access */
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function meetsContentEntityRoutesCondition(Route $outbound_route, Request $request): bool {
    // The parent class requires that the current path/request must be an entity
    // route of the same type as the outbound route.
    // We are not in the same use case as we also want to alter routes when we
    // are on /admin/content for example, so skip that check.
    // @see Parent class origins https://www.drupal.org/project/drupal/issues/1810394
    $outbound_path_pattern = $outbound_route->getPath();
    $storage = $this->paths[$request] ?? [];
    if (!isset($storage[$outbound_path_pattern])) {
      $storage[$outbound_path_pattern] = FALSE;

      if (!empty($this->getContentEntityPaths()[$outbound_path_pattern])) {
        $storage[$outbound_path_pattern] = TRUE;
      }

      $this->paths[$request] = $storage;
    }

    return $storage[$outbound_path_pattern];
  }

}
