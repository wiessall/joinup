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
