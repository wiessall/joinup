<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\joinup_discussion\Form\InviteToDiscussionForm;
use Drupal\joinup_invite\Form\InviteToGroupForm;
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
   *   The human-readable node field alias.
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
        'creation date' => 'created',
        'description' => 'description',
        'featured' => 'featured',
        'keywords' => 'keywords',
        'logo' => 'logo',
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
        'keywords' => 'keywords',
        'logo' => 'field_custom_page_logo',
        'state' => 'field_state',
      ],
      'discussion' => [
        'attachments' => 'field_attachment',
        'content' => 'body',
        'keywords' => 'keywords',
        'logo' => 'field_discussion_logo',
        'published at' => 'published_at',
        'state' => 'field_state',
      ],
      'document' => [
        'document type' => 'field_type',
        'body' => 'field_paragraphs_body',
        'file' => 'resources',
        'keywords' => 'keywords',
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
        'EC event' => 'european_commission_digit_event',
        'keywords' => 'keywords',
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
        'body' => 'field_paragraphs_body',
        'headline' => 'field_news_headline',
        'keywords' => 'keywords',
        'logo' => 'field_news_logo',
        'published at' => 'published_at',
        'spatial coverage' => 'spatial_coverage',
        'state' => 'field_state',
      ],
      'owner' => [
        'name' => 'title',
        'owner type' => 'owner_type',
        'owner country' => 'country',
        'state' => 'field_state',
        'author' => 'uid',
      ],
      'distribution' => [
        'access url' => 'resources',
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
        'keywords' => 'keywords',
        'spatial coverage' => 'spatial_coverage',
        'status' => 'process_status',
        'state' => 'field_state',
        'creation date' => 'created',
        'modification date' => 'changed',
        'published at' => 'published_at',
        'resources' => 'resources',
        'release notes' => 'description',
      ],
      'solution' => [
        'author' => 'uid',
        'uri' => 'uri',
        'title' => 'title',
        'short ID' => 'short_id',
        'abstract' => 'abstract',
        'contact information' => 'contact',
        'creation date' => 'created',
        'description' => 'description',
        'resources' => 'resources',
        'domain' => 'domain',
        'keywords' => 'keywords',
        'landing page' => 'landing_page',
        'language' => 'language',
        'logo' => 'logo',
        'metrics page' => 'metrics_page',
        'modification date' => 'changed',
        'owner' => 'owner',
        'topic' => 'field_topic',
        'related by type' => 'list_related_entities',
        'related solutions' => 'related_solutions',
        'solution classification' => 'classification',
        'solution type' => 'solution_type',
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
        'shared at' => 'field_shared_in__at',
        'solution' => OgGroupAudienceHelperInterface::DEFAULT_FIELD,
        'topic' => 'field_topic',
        'visits' => 'visit_count',
      ],
    ];

    // Check both the node type specific aliases and the shared aliases.
    foreach ([$type, 'shared'] as $key) {
      if (!empty($aliases[$key][$alias])) {
        return $aliases[$key][$alias];
      }
    }

    return $alias;
  }

  /**
   * Returns field mappings for a given taxonomy type.
   *
   * @param string $type
   *   The taxonomy type.
   *
   * @return array
   *   Field mappings.
   */
  protected function getTaxonomyFieldMappings(string $type): array {
    $aliases = [
      'spdx_licence' => [
        'uri' => 'uri',
        'ID' => 'spdx_id',
        'title' => 'name',
        'see also' => 'see_also',
        'text' => 'description',
      ],
    ];

    if (!isset($aliases[$type])) {
      throw new \InvalidArgumentException("Invalid taxonomy type, '$type' not found.");
    }

    return $aliases[$type];
  }

  /**
   * Returns information about the invitations related to the given entity type.
   *
   * @param string $type
   *   The entity type for which to return relevant invitation info.
   *
   * @return array
   *   An associative array with the following keys:
   *   - invitation_bundle: The ID of the InvitationType that is associated with
   *     inviting people to content of the given entity type.
   *   - message_template: The message template which is used to generate
   *     invitation notifications when people are invited to content of the
   *     given entity type.
   */
  protected function getInvitationInfo(string $type): array {
    $mapping = [
      'discussion' => [
        'invitation_bundle' => 'discussion',
        'message_template' => InviteToDiscussionForm::TEMPLATE_DISCUSSION_INVITE,
      ],
      'collection' => [
        'invitation_bundle' => 'group_membership',
        'message_template' => InviteToGroupForm::TEMPLATES['collection'],
      ],
      'solution' => [
        'invitation_bundle' => 'group_membership',
        'message_template' => InviteToGroupForm::TEMPLATES['solution'],
      ],
    ];

    if (!isset($mapping[$type])) {
      throw new \InvalidArgumentException("Invalid invitation info type, '$type' not found.");
    }

    return $mapping[$type];
  }

  /**
   * Returns the mapping of logo field to their content type.
   *
   * @param string $type
   *   The node type for which to return the field name.
   *
   * @return array
   *   Mapping.
   */
  protected function getImageLibraryBrowserMapping(string $type): array {
    $mappings = [
      'collection' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'logo',
        ],
      ],
      'solution' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'logo',
        ],
      ],
      'event' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_event_logo',
        ],
      ],
      'news' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_news_logo',
        ],
      ],
      'discussion' => [
        'type' => 'node',
        'fields' => [
          'logo' => 'field_discussion_logo',
        ],
      ],
    ];

    if (!isset($mappings[$type])) {
      throw new \InvalidArgumentException("Invalid image library type, '$type' not found.");
    }

    return $mappings[$type];
  }

  /**
   * Mapping of human-readable entity type names to machine names.
   *
   * @return array
   *   The entity type mapping.
   */
  protected function entityTypeAliases(): array {
    return [
      'announcement' => 'message',
      'assessment' => 'assessment',
      'collection' => 'node',
      'contact information' => 'node',
      'contact_information' => 'node',
      'content' => 'node',
      'custom page' => 'node',
      'custom_page' => 'node',
      'discussion' => 'node',
      'distribution' => 'node',
      'document' => 'node',
      'event' => 'node',
      'glossary' => 'node',
      'group' => 'node',
      'licence' => 'node',
      'menu link' => 'menu_link_content',
      'news' => 'node',
      'oss_solution' => 'node',
      'owner' => 'node',
      'release' => 'node',
      'report' => 'assessment',
      'solution' => 'node',
      'spdx licence' => 'taxonomy_term',
      'term' => 'taxonomy_term',
      'topic' => 'taxonomy_term',
      'user' => 'user',
      'webform' => 'webform',
      'keyword' => 'taxonomy_term',
    ];
  }

  /**
   * Mapping of human readable bundle names to machine names.
   *
   * @return array
   *   The bundle mapping.
   */
  protected function bundleAliases(): array {
    return [
      'assessment' => 'report',
      'collection' => 'collection',
      'contact information' => 'contact_information',
      'custom page' => 'custom_page',
      'discussion' => 'discussion',
      'distribution' => 'distribution',
      'document' => 'document',
      'event' => 'event',
      'glossary' => 'glossary',
      'licence' => 'licence',
      'news' => 'news',
      'oss_solution' => 'oss_solution',
      'owner' => 'owner',
      'release' => 'release',
      'spdx licence' => 'spdx_licence',
      'solution' => 'solution',
      'keyword' => 'tags',
    ];
  }

  /**
   * Maps an alias to an actual facet id.
   *
   * The facet id is used as "drupal-data-facet-id" property.
   *
   * @param string $alias
   *   The facet alias.
   *
   * @return string
   *   The facet id.
   *
   * @throws \Exception
   *   Thrown when the mapping is not found.
   */
  protected function getFacetIdFromAlias(string $alias): string {
    $mappings = [
      'Collection event date' => 'collection_event_type',
      'Content listing types' => 'content_listing_type',
      'Content types' => 'type',
      'Solution Domain' => 'domain',
      'EIF recommendations' => 'category',
      'Entity bundle' => 'bookmark_entity_bundle',
      'Event date' => 'event_date',
      'Interoperable' => 'interoperable_flag',
      'My collections content' => 'collection_my_content',
      'My content' => 'content_my_content',
      'My solutions content' => 'solution_my_content',
      'Status' => 'status',
      'User content' => 'user_content_bundle',
      'collection content archived toggle' => 'collection_content_status',
      'collection topic' => 'collection_topic',
      'collection type' => 'collection_type',
      'collection/solution' => 'group',
      'eif conceptual model' => 'conceptual_model',
      'eif interoperability layer' => 'interoperability_layer',
      'eif principle' => 'principle',
      'solution content archived toggle' => 'solution_content_status',
      'solution spatial coverage' => 'solution_spatial_coverage',
      'solution topic' => 'solution_topic',
      'spatial coverage' => 'spatial_coverage',
      'topic' => 'topic',
      'Type of document' => 'type_of_document',
      'Organisation country' => 'organisation_country',
      'OSS category' => 'oss_category',
      'OSS intended audience' => 'oss_scope',
      'OSS development status' => 'oss_development_status',
      'OSS software type' => 'oss_software_type',
      'OSS catalog' => 'oss_source',
    ];

    if (!isset($mappings[$alias])) {
      throw new \InvalidArgumentException("No facet id mapping found for '$alias'.");
    }

    return $mappings[$alias];
  }

  /**
   * Returns field machine name by label.
   *
   * @param string $label
   *   Field label.
   *
   * @return string[]
   *   Array of label mapping.
   */
  protected function getNodeFieldNameFromLabel(string $label): string {
    $mapping = [
      'Country' => 'country',
      'Domain' => 'domain',
      'Geographical coverage' => 'spatial_coverage',
      'Language availability' => 'language',
      'Licence legal type' => 'legal_type',
      'Related solutions' => 'related_solutions',
      'Scope' => 'field_scope',
      'Search keywords' => 'keywords',
      'Solution type' => 'solution_type',
      'Solution classification' => 'classification',
      'Topic' => 'field_topic',
      'Type' => 'owner_type',
    ];
    return $mapping[$label];
  }

}
