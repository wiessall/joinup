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
    Then I should see the text "Moderate" in the "Review Report" row
    And I should not see the text "Moderate" in the "Draft Report" row
    And I should not see the text "Moderate" in the "Published Report" row

    Given I am logged in as a user with the "Moderator" role
    When I visit "/admin/content/assessment"
    Then I should not see the text "Moderate" in the "Review Report" row
    And I should not see the text "Moderate" in the "Draft Report" row
    And I should not see the text "Moderate" in the "Published Report" row

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

  Scenario: Test assessment reports repository view
    Given the assessments collection exists

    # Test as anonymous.
    When I am not logged in
    And I visit "collection/assessments/report/repository"
    Then I should see the heading "Assessment reports"
    And I should not see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the link "Search assessments"
    And I should see the text "There are no published assessment reports yet."
    Given report assessments:
      | name                        | state     |
      | Weekly Health Check         | published |
      | Portal Accessibility Review | review    |
      | System Audit Draft          | draft     |
    And I reload the page
    And the page should show only the tiles "Weekly Health Check"
    When I go to "/user/assessments"
    Then I should see the heading "Sign in to continue"

    # Test as logged in user.
    Given the following users:
      | Username         |
      | Lorian Nightfall |
    And I am logged in as "Lorian Nightfall"
    And I visit "collection/assessments/report/repository"
    Then I should see the heading "Assessment reports"
    And I should see the link "New assessment"
    And I should see the link "My assessments"
    And I should see the link "Search assessments"
    And the page should show only the tiles "Weekly Health Check"
    When I follow "My assessments"
    Then I should be on "/user/assessments"
    Then I should see the heading "My assessment reports"
    And I should see the link "New assessment"
    And I should not see the link "My assessments"
    And I should see the link "Search assessments"
    And I should see the text "There are no assessment reports yet for the current user."
    Given report assessments:
      | name                       | state     | uid              |
      | Alpha Release Summary      | published | Lorian Nightfall |
      | Beta Feature Review        | review    | Lorian Nightfall |
      | Prototype Evaluation Draft | draft     | Lorian Nightfall |
    And I reload the page
    And the page should show only the tiles "Alpha Release Summary, Beta Feature Review, Prototype Evaluation Draft"

    # Ensure the reports view only applies to the assessment collection.
    When I am not logged in
    And I go to "/collection/portal-support/report/repository"
    Then I should get a 404 HTTP response
