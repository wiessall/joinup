@api @group-j
Feature: Completing translation jobs for distributions
  As a moderator reviewing machine translations
  I want to accept a distribution's translation with "Save as completed"
  So that the translation is published without the site crashing.

  Background:
    Given the following "solution" content:
      | title         |
      | Test solution |
    And the following "distribution" content:
      | title             | solution      |
      | Test distribution | Test solution |

  Scenario: Accept a distribution translation whose parent release is missing
    Given the release referenced by the "Test distribution" distribution no longer exists
    And a translation job in review to "bg" for the "Test distribution" distribution

    When I am logged in as a user with the "administer tmgmt" permission
    And I visit the translation review form of the "Test distribution" distribution
    And I press "Save as completed"
    Then I should not see the text "We cannot serve your request"
    And the "node" entity with label "Test distribution" should have a "bg" translation
