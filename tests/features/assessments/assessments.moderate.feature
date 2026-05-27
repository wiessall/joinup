@api @group-c
Feature: Assessment report moderation access control
  Tests access restrictions for the assessment report moderate form.

  Background:
    Given public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |

  Scenario: Access to moderate form is restricted by state
    Given report assessments:
      | name                | state     | provider          |
      | Draft Report        | draft     | Test Organization |
      | Published Report    | published | Test Organization |

    Given I am logged in as a moderator
    And I go to the moderate form of the "Draft Report" assessment report
    Then I should see the heading "Access denied"

    When I go to the moderate form of the "Published Report" assessment report
    Then I should see the heading "Access denied"

  # Regression test for ISAICP-11345.
  Scenario: Publishing a report through the moderation form sets the publication date
    Given the assessments collection exists
    And expression assessments:
      | name                 | description                         |
      | Publish Date Express | Expression used for publish-at test |
    And public_service assessments:
      | name                 |
      | Publish Date Service |
    And report assessments:
      | name                | state  | provider          |
      | Publish Date Report | review | Test Organization |
    And the following report has binding requirement:
      | report     | Publish Date Report  |
      | expression | Publish Date Express |
      | service    | Publish Date Service |

    When I am logged in as a assessment_moderator
    And I go to the moderate form of the "Publish Date Report" assessment report
    And I press "Publish"
    Then I should see the success message containing "The assessment report has been published"

    When I visit "collection/assessments/report/repository"
    Then the page should show only the tiles "Publish Date Report"
    And I should see the text "Published:"
