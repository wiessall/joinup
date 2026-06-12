@api @group-g
Feature: Translated content does not advertise its translations in the page head.
  In order to avoid exposing translation URLs to crawlers
  As the site owner
  I do not want alternate hreflang <link> tags rendered in the page head for
  translated content.

  Scenario: A translated news page has no alternate hreflang links in the head.
    Given the following "collection" content:
      | title        |
      | News Central |
    And the following "news" content:
      | title          | headline       | collection   | state     |
      | Breaking Story | Latest updates | News Central | published |
    And the following news entity translations:
      | source         | language | title             |
      | Breaking Story | el       | Breaking GR Story |

    When I am an anonymous user
    And I go to the "Breaking Story" news
    Then I should see the heading "Breaking Story"
    # The content_translation module would otherwise add, for each available
    # translation, a <link rel="alternate" hreflang="..."> tag pointing to the
    # translated canonical URL.
    # @see joinup_i18n_page_attachments_alter()
    But the response should not contain "<link rel=\"alternate\" hreflang="

    When I go to the "el" version of the "Breaking Story" news
    # After ISAICP-11420, this should be changed to be able to view the
    # heading.
    Then I should not see the heading "Breaking GR Story"
    But the response should not contain "<link rel=\"alternate\" hreflang="
