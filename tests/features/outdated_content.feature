@javascript @api
Feature:
  - As a moderator I want to be able to manage the outdated content thresholds.
  - As a user, regardless of role, I want to see a notice on outdated content.

  Scenario: Test moderator and anonymous user perspectives.

    Given the following "collection" content:
      | title            |
      | Outdated content |
    Given the following "solution" content:
      | title             | collection       |
      | Recent solution   | Outdated content |
      | Outdated solution | Outdated content |
    And the following "discussion" content:
      | title                         | collection       | published at | created   | state     |
      | Recent published discussion   | Outdated content | -2 years     | -2 years  | published |
      | Outdated published discussion | Outdated content | -11 years    | -11 years | published |
      | Not published discussion      | Outdated content |              | -12 years | draft     |
    And the following "document" content:
      | title             | collection       | published at |
      | Very old document | Outdated content | 1985-03-04   |
    And the following "event" content:
      | title                    | collection       | published at     | created          | state     |
      | Recent published event   | Outdated content | -3 months        | -3 months        | published |
      | Outdated published event | Outdated content | -1 year -1 month | -1 year -1 month | published |
      | Not published event      | Outdated content |                  | -31 years        | draft     |
    And the following "news" content:
      | title                            | collection       | published at      | created           | state     |
      | Recent published news            | Outdated content | -7 months         | -7 months         | published |
      | Outdated published news          | Outdated content | -3 years -1 month | -3 years -1 month | published |
      | Outdated archived published news | Outdated content | -3 years -1 month | -3 years -1 month | archived  |
      | Not published news               | Outdated content |                   | -40 years         | draft     |

    Given I am logged in as a moderator
    When I click "Outdated content thresholds"
    Then I should see the heading "Outdated content thresholds"
    When I check the "Discussion (Content)" row
    And I fill in "config[node:discussion][threshold]" with "4"
    And I check the "Event (Content)" row
    And I fill in "config[node:event][threshold]" with "1"
    And I check the "News (Content)" row
    And I fill in "config[node:news][threshold]" with "1"
    When I press "Save configuration"

    # Test as anonymous. This covers all roles.
    Given I am an anonymous user

    # Discussion is prone to be outdated.
    When I go to the "Recent published discussion" discussion
    Then I should see the heading "Recent published discussion"
    But I should not see "This discussion is more than"

    When I go to the "Outdated published discussion" discussion
    Then I should see the heading "Outdated published discussion"
    And the text "This discussion is more than 11 years old" should appear 1 time

    # A document is not prone to be outdated.
    When I go to the "Very old document" document
    Then I should see the heading "Very old document"
    But I should not see "This document is more than"

    # Event is prone to be outdated.
    When I go to the "Recent published event" event
    Then I should see the heading "Recent published event"
    But I should not see "This event is more than"

    When I go to the "Outdated published event" event
    Then I should see the heading "Outdated published event"
    And the text "This event is more than 1 year old" should appear 1 time

    # News item is prone to be outdated.
    When I go to the "Recent published news" news
    Then I should see the heading "Recent published news"
    But I should not see "This news is more than"

    When I go to the "Outdated published news" news
    Then I should see the heading "Outdated published news"
    And the text "This news is more than 3 years old" should appear 1 time

    When I go to the "Outdated archived published news" news
    Then I should see the heading "Outdated archived published news"
    And the text "This news is more than 3 years old" should appear 0 time

    # Login as moderator to test content that was never published.
    Given I am logged in as a moderator

    # Content never published is not prone to be outdated.
    When I go to the "Not published discussion" discussion
    Then I should see the heading "Not published discussion"
    But I should not see "This discussion is more than"

    When I go to the "Not published event" event
    Then I should see the heading "Not published event"
    But I should not see "This event is more than"

    When I go to the "Not published news" news
    Then I should see the heading "Not published news"
    But I should not see "This news is more than"

    # Cleanup configuration.
    When I click "Outdated content thresholds"
    And I uncheck the "Discussion (Content)" row
    And I uncheck the "Event (Content)" row
    And I uncheck the "News (Content)" row
    When I press "Save configuration"
