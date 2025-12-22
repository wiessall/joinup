@api @group-c
Feature: Assessment listing tests.

  Background:
    Given public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |

  Scenario: Moderation link visibility on assessment content overview
    Given report assessments:
      | name             | state     | provider          |
      | Review Report    | review    | Test Organization |
      | Draft Report     | draft     | Test Organization |
      | Published Report | published | Test Organization |

    Given I am logged in as a user with the "Assessment moderator" role
    When I visit "/admin/content/assessment"
    Then I should see "Moderate" in the "Review Report" row
    And I should not see "Moderate" in the "Draft Report" row
    And I should not see "Moderate" in the "Published Report" row

    Given I am logged in as a user with the "Moderator" role
    When I visit "/admin/content/assessment"
    Then I should not see "Moderate" in the "Review Report" row
    And I should not see "Moderate" in the "Draft Report" row
    And I should not see "Moderate" in the "Published Report" row

  Scenario: ID links on admin listing page navigate to correct assessment page
    Given report assessments:
      | name                        | state     |
      | Weekly Health Check         | published |
      | Portal Accessibility Review | review    |
    Given I am logged in as a user with the "Assessment moderator" role
    When I visit "admin/content/assessment"
    Then I should see the heading "Assessments admin"
    When I click the ID link for the "Weekly Health Check" assessment
    Then I should see the heading "Weekly Health Check"
    When I visit "admin/content/assessment"
    And I click the ID link for the "Portal Accessibility Review" assessment
    Then I should see the heading "Portal Accessibility Review"
