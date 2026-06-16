@api @group-g
Feature: Schedule translation for a collection
  In order to queue content for translation
  As a moderator
  I need to be able to schedule translation for a collection and its published content.

  Scenario: Moderator can schedule translation for a collection
    Given the following "collection" content:
      | title                | state     |
      | Translation Test Col | published |
    And the following "solution" content:
      | title               | collection           | state     |
      | Translation Test Sol| Translation Test Col | published |
    And the following "news" content:
      | title                          | solution             | state     |
      | Translation Test News 1        | Translation Test Sol | published |
      | Translation Test News 2        | Translation Test Sol | published |
      | Translation Test News Archived | Translation Test Sol | published |
    And the workflow state of the "Translation Test News Archived" content is changed to "archived"
    And the following "custom_page" content:
      | title                        | collection           | state     |
      | Translation Test Custom Page | Translation Test Col | published |

    Given I am logged in as a "moderator"
    When I go to "/dashboard/schedule-translation"
    Then I should see the heading "Schedule translation"
    And I should see the link "Translation Test Col"

    When I click the radio button in the "Translation Test Col" row
    And I press "Schedule translation"
    And I wait for the batch process to finish
    Then I should see the success message containing "Translation scheduled: 5"
