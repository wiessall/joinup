@api @group-c
Feature: Assessment listing tests.

  Background:
    Given public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |

  Scenario: ID links on admin listing page navigate to correct assessment page
    Given report assessments:
      | name                        | state     |
      | Weekly Health Check         | published |
      | Portal Accessibility Review | review    |
    And I am logged in as a "moderator"
    When I visit "admin/content/assessment"
    Then I should see the heading "Assessments admin"
    When I click the ID link for the "Weekly Health Check" assessment
    Then I should see the heading "Weekly Health Check"
    When I visit "admin/content/assessment"
    And I click the ID link for the "Portal Accessibility Review" assessment
    Then I should see the heading "Portal Accessibility Review"
