<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Name mappings.
 */
trait NameMappingsTrait {

  /**
   * Returns the actual field name for a given node field alias.
   *
   * @param string $type
   *   The node type for which to return the field name.
   * @param string $alias
   *   The human readable node field alias.
   *
   * @return string
   *   The actual field name, or the original string if the alias is not known.
   */
  protected function getNodeFieldNameFromAlias(string $type, string $alias): string {
    $aliases = [
      'collection' => [
        'author' => 'uid',
        'uri' => 'uri',
        'title' => 'title',
        'short ID' => 'short_id',
        'abstract' => 'abstract',
        'access url' => 'website_url',
        'closed' => 'closed_group',
        'contact information' => 'contact',
        'content creation' => 'content_creation',
        'creation date' => 'created',
        'description' => 'description',
        'featured' => 'featured',
        'keywords' => 'field_keywords',
        'logo' => 'logo',
        'moderation' => 'group_moderation',
        'modification date' => 'changed',
        'owner' => 'owner',
        'published at' => 'published_at',
        'spatial coverage' => 'spatial_coverage',
        'state' => 'field_state',
      ],
      'contact_information' => [
        'email' => 'email',
        'name' => 'title',
        'website url' => 'webpage',
        'author' => 'uid',
        'state' => 'field_state',
      ],
      'custom_page' => [
        'attachments' => 'field_attachment',
        'body' => 'field_paragraphs_body',
        'keywords' => 'field_keywords',
        'logo' => 'field_custom_page_logo',
        'state' => 'field_state',
      ],
      'discussion' => [
        'attachments' => 'field_attachment',
        'content' => 'body',
        'keywords' => 'field_keywords',
        'logo' => 'field_discussion_logo',
        'published at' => 'published_at',
        'state' => 'field_state',
      ],
      'document' => [
        'document publication date' => 'field_document_publication_date',
        'document type' => 'field_type',
        'file' => 'field_file',
        'keywords' => 'field_keywords',
        'licence' => 'field_licence',
        'logo' => 'field_document_logo',
        'published at' => 'published_at',
        'short title' => 'field_short_title',
        'spatial coverage' => 'spatial_coverage',
        'state' => 'field_state',
      ],
      'event' => [
        'agenda' => 'field_event_agenda',
        'body' => 'field_paragraphs_body',
        'end date' => 'field_event_date:end_value',
        'keywords' => 'field_keywords',
        'location' => 'field_location',
        'logo' => 'field_event_logo',
        'online location' => 'field_event_online_location',
        'organisation' => 'field_organisation',
        'published at' => 'published_at',
        'scope' => 'field_scope',
        'short title' => 'field_short_title',
        'spatial coverage' => 'spatial_coverage',
        'start date' => 'field_event_date:value',
        'state' => 'field_state',
        'web url' => 'field_event_web_url',
      ],
      'glossary' => [
        'definition' => 'field_glossary_definition:value',
        'summary' => 'field_glossary_definition:summary',
        'synonyms' => 'field_glossary_synonyms',
      ],
      'licence' => [
        'licence type' => 'licence_type',
        'spdx licence' => 'spdx_licence',
        'legal type' => 'legal_type',
      ],
      'news' => [
        'headline' => 'field_news_headline',
        'keywords' => 'field_keywords',
        'logo' => 'field_news_logo',
        'published at' => 'published_at',
        'spatial coverage' => 'spatial_coverage',
        'state' => 'field_state',
      ],
      'owner' => [
        'name' => 'title',
        'owner type' => 'owner_type',
        'state' => 'field_state',
        'author' => 'uid',
      ],
      'distribution' => [
        'access url' => 'access_url',
        'creation date' => 'created',
        'description' => 'description',
        'downloads' => 'downloads',
        'file size' => 'file_size',
        'format' => 'format',
        'licence' => 'field_licence',
        'modification date' => 'changed',
        'representation technique' => 'representation_technique',
        'release' => 'release',
        'solution' => 'og_audience',
        'state' => 'field_state',
        'status' => 'process_status',
        'title' => 'title',
        'uri' => 'uri',
        'published at' => 'published_at',
      ],
      'release' => [
        'uri' => 'uri',
        'is version of' => 'og_audience',
        'title' => 'title',
        'release number' => 'release_number',
        'keywords' => 'field_keywords',
        'spatial coverage' => 'spatial_coverage',
        'status' => 'process_status',
        'state' => 'field_state',
        'creation date' => 'created',
        'modification date' => 'changed',
        'published at' => 'published_at',
        'documentation' => 'documentation',
        'release notes' => 'description',
      ],
      'solution' => [
        'author' => 'uid',
        'uri' => 'uri',
        'title' => 'title',
        'short ID' => 'short_id',
        'abstract' => 'abstract',
        'contact information' => 'contact',
        'content creation' => 'content_creation',
        'creation date' => 'created',
        'description' => 'description',
        'documentation' => 'documentation',
        'keywords' => 'field_keywords',
        'landing page' => 'landing_page',
        'language' => 'language',
        'logo' => 'logo',
        'metrics page' => 'metrics_page',
        'moderation' => 'group_moderation',
        'modification date' => 'changed',
        'owner' => 'owner',
        'topic' => 'field_topic',
        'related by type' => 'list_related_entities',
        'related solutions' => 'related_solutions',
        'solution type' => 'solution_type',
        'spatial coverage' => 'spatial_coverage',
        'status' => 'field_status',
        'state' => 'field_state',
        'collection' => 'og_audience',
        'collections' => 'og_audience',
        'featured' => 'featured',
        'pinned in' => 'pinned_in_collection',
        'published at' => 'published_at',
        'unpinned in' => 'unpinned_in_collection',
      ],
      'shared' => [
        'collection' => OgGroupAudienceHelperInterface::DEFAULT_FIELD,
        'shared on' => 'field_shared_in',
        'solution' => OgGroupAudienceHelperInterface::DEFAULT_FIELD,
        'topic' => 'field_topic',
        'visits' => 'visit_count',
      ],
    ];

    // Check both the node type specific aliases as well as the shared aliases.
    foreach ([$type, 'shared'] as $key) {
      if (!empty($aliases[$key][$alias])) {
        return $aliases[$key][$alias];
      }
    }

    return $alias;
  }

}
