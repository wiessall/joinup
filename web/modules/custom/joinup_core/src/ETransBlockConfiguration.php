<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\GlossaryTermInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_document\Entity\DocumentInterface;
use Drupal\joinup_event\Entity\EventInterface;
use Drupal\joinup_news\Entity\NewsInterface;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Provides the Webtools eTrans block configuration for routes and bundles.
 *
 * We offer machine translation for various content pages, using the Webtools
 * eTrans service. Since every page type has a different layout we need a unique
 * configuration for each, detailing the HTML elements that should be translated
 * and the ones that have to be excluded from translation.
 *
 * Some suggestions, inspired by the Webtools eTrans documentation:
 *   - Only translate the actual content, avoid translating generic elements
 *     that occur on multiple pages, such as the site navigation, header,
 *     footer, pagers, share links etc.
 *   - Do not translate elements which are mainly intended for linking to other
 *     pages, such as tiles, attachments, external links etc.
 *   - Avoid translating names of any kind if possible.
 *   - Only translate titles if they are part of the content, do not translate
 *     generic titles such as "About" or "Overview".
 *
 * @see \Drupal\oe_webtools_etrans\Plugin\Block\ETransBlock
 */
class ETransBlockConfiguration {

  /**
   * Maps the Webtools eTrans block configuration to routes and bundles.
   */
  public const CONFIG_MAPPING = [
    'bundles' => [
      CustomPageInterface::class => [
        'include' => 'h1.page-title__title,#block-ventuno-content',
        'exclude' => '.action-link',
      ],
      DiscussionInterface::class => [
        'include' => '.page-title,#block-ventuno-content',
        'exclude' => '.page-title__details a[title="View user profile."],.action-link,section.comments',
      ],
      DocumentInterface::class => [
        'include' => '.page-title,.page__content,.fieldset--separated .page__content',
        'exclude' => '.page-title .details__element > a,.fieldset__field--files,.action-link__wrapper',
      ],
      EventInterface::class => [
        'include' => '.page-title,#block-ventuno-content,#block-iop-content',
        'exclude' => '.action-link,section.comments,.event__field-shared-in',
      ],
      GlossaryTermInterface::class => [
        'include' => 'h1.page-title__title,#block-ventuno-content',
      ],
      NewsInterface::class => [
        'include' => '.page-title,#block-ventuno-content,#block-iop-content',
        'exclude' => '.page-title__details a[title="View user profile."],.action-link,.news__field-shared-in,.news__referenced-solution,section.comments',
      ],
      CollectionInterface::class => [
        'include' => '.layout-content .layout',
        'exclude' => '.block-field-blockrdf-entitycollectionfield-collection-content',
      ],
      SolutionInterface::class => [
        'include' => '.layout-content .layout',
        'exclude' => '.block-field-blockrdf-entitysolutionfield-is-content, .block-extra-field-blockrdf-entitysolutionrelated-solutions',
      ],
    ],
    'routes' => [
      // The "Glossary" page of a collection.
      'entity.rdf_entity.glossary' => [
        'include' => '.view-glossary',
        'exclude' => 'h2.h3,.views-field-title-1,.views-field-field-glossary-synonyms',
      ],
    ],
  ];

}
