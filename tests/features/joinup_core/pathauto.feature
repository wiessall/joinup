@api @group-d
Feature:
  As an owner of a website
  In order to provide the visitors with user friendly urls
  I need to have url aliases generated automatically.

  Scenario: Entities should have distinct pathauto aliases.
    Given the following "collection" content:
      | title               | logo     |
      | Pathauto collection | logo.png |
    And the following "solution" content:
      | title             | description       | collection          |
      | Pathauto solution | Pathauto solution | Pathauto collection |
    And the following "release" content:
      | title            | release number | release notes    | is version of     |
      | Pathauto release | 23             | Pathauto release | Pathauto solution |
    And the following "distribution" content:
      | title                 | description           | solution          |
      | Pathauto distribution | Pathauto distribution | Pathauto solution |
    And the following "licence" content:
      | title            | description      |
      | Pathauto licence | Pathauto licence |
    And the following "discussion" content:
      | title                 | body                  | collection          | solution          |
      | Pathauto discussion   | Pathauto discussion   | Pathauto collection |                   |
      | Pathauto discussion 2 | Pathauto discussion 2 |                     | Pathauto solution |
    And the following "document" content:
      | title               | field_paragraphs_body | collection          | solution          |
      | Pathauto document   | Pathauto document     | Pathauto collection |                   |
      | Pathauto document 2 | Pathauto document 2   |                     | Pathauto solution |
    And the following "event" content:
      | title            | field_paragraphs_body | collection          | solution          |
      | Pathauto event   | Pathauto event        | Pathauto collection |                   |
      | Pathauto event 2 | Pathauto event 2      |                     | Pathauto solution |
    And the following "news" content:
      | title           | body            | collection          | solution          |
      | Pathauto news   | Pathauto news   | Pathauto collection |                   |
      | Pathauto news 2 | Pathauto news 2 |                     | Pathauto solution |
    And the following "custom_page" content:
      | title                    | body          | collection          | solution          |
      | Pathauto collection page | Pathauto page | Pathauto collection |                   |
      | Pathauto solution page   | Pathauto page |                     | Pathauto solution |

    When I go to the "Pathauto collection" collection
    Then the url should match "collection/pathauto-collection"
    When I go to the "Pathauto solution" solution
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution"
    When I go to the "Pathauto release" release
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/release/23"
    When I go to the "Pathauto distribution" distribution
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/distribution/pathauto-distribution"
    When I visit the "Pathauto document" document
    Then the url should match "collection/pathauto-collection/document/pathauto-document"
    When I visit the "Pathauto document 2" document
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/document/pathauto-document-2"
    When I visit the "Pathauto discussion" discussion
    Then the url should match "collection/pathauto-collection/discussion/pathauto-discussion"
    When I visit the "Pathauto discussion 2" discussion
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/discussion/pathauto-discussion-2"
    When I visit the "Pathauto event" event
    Then the url should match "collection/pathauto-collection/event/pathauto-event"
    When I visit the "Pathauto event 2" event
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/event/pathauto-event-2"
    When I visit the "Pathauto news" news
    Then the url should match "collection/pathauto-collection/news/pathauto-news"
    When I visit the "Pathauto news 2" news
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/news/pathauto-news-2"
    When I visit the "Pathauto collection page" "custom page"
    Then the url should match "collection/pathauto-collection/pathauto-collection-page"
    When I visit the "Pathauto solution page" "custom page"
    Then the url should match "collection/pathauto-collection/solution/pathauto-solution/pathauto-solution-page"
