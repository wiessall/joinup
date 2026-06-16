@api @group-g
Feature: Translations are restricted and not advertised to anonymous users.
  In order to avoid exposing translation URLs to crawlers
  As the site owner
  I do not want content translations to be viewable by - or advertised through
  alternate hreflang <link> tags to - users without permission.

  Background:
    Given the following "collection" content:
      | title        |
      | News Central |
    And the following "news" content:
      | title          | headline       | collection   | state     |
      | Breaking Story | Latest updates | News Central | published |
    And the following news entity translations:
      | source         | language | title             |
      | Breaking Story | el       | Breaking GR Story |

  Scenario: Anonymous users are not exposed to the Greek translation.
    When I am an anonymous user
    And I go to the "Breaking Story" news
    Then I should see the heading "Breaking Story"
    # The Greek translation is not viewable by anonymous users, so
    # content_translation does not render an alternate hreflang link for it.
    # @see joinup_i18n_entity_access()
    But the response should not contain "<link rel=\"alternate\" hreflang=\"el\""

    # Visiting the Greek URL falls back to English for anonymous users; the
    # translation is never served and its hreflang link is never advertised.
    When I go to the "el" version of the "Breaking Story" news
    Then I should see the heading "Breaking Story"
    But the response should not contain "<link rel=\"alternate\" hreflang=\"el\""

  Scenario: Moderators can view the Greek translation and its hreflang link.
    When I am logged in as a moderator
    And I go to the "Breaking Story" news
    # Moderators hold the "view content translations" permission, so the
    # alternate hreflang link to the Greek translation is advertised to them.
    Then the response should contain "<link rel=\"alternate\" hreflang=\"el\""
    When I go to the "el" version of the "Breaking Story" news
    Then I should see the heading "Breaking GR Story"
