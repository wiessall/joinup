@api @group-c @wip
Feature: Assessment report moderation workflow
  Tests moderation workflow for assessment reports.

  Background:
    Given users:
      | Username       | Roles               | E-mail                  | First name | Family name |
      | Regular User   |                     | regular@example.com     | Regular    | User        |
      | Reporter User  | assessment_reporter | reporter@example.com    | Reporter   | User        |
      | Moderator User | moderator           | moderator@example.com   | Moderator  | User        |

  Scenario: Available state transitions depend on user permissions
    Given I am logged in as "Moderator User"
    When I visit "/assessments/add/report"
    Then I should see the button "Published"
    And I should not see the button "Draft"

    When I am logged in as "Regular User"
    And I visit "/assessments/add/report"
    Then I should not see the button "Published"
    And I should see the button "Draft"
    And I should see the button "In Review"

  Scenario: User with bypass permission creates assessment report in published state
    Given I am logged in as "Reporter User"
    And report assessment content:
      | title                  | state     | created              | organization         | collection                         | author        |
      | Published Assessment 1 | published | 2020-01-01T00:00:00Z | Test Organization    | Interoperability assessments space | Reporter User |

    # Verify the report is published and visible.
    When I am an anonymous user
    And I go to "/collection/assessments/assessment-reports"
    Then I should see "Published Assessment 1"

  Scenario: Moderator can access moderate form with correct permission
    Given report assessment content:
      | title              | state     | created              | organization         | author       |
      | Pending Assessment | draft     | 2020-01-01T00:00:00Z | Test Organization    | Regular User |

    # Moderator with permission can access the moderate form.
    When I am logged in as "Moderator User"
    And I go to the moderate form of the "Pending Assessment" assessment report
    Then the response status code should be 200
    And I should see "Pending Assessment"

    # Regular user cannot access the moderate form.
    Given I am logged in as "Regular User"
    When I go to the moderate form of the "Pending Assessment" assessment report
    Then the response status code should be 403

  Scenario: Moderator publishes draft assessment report
    Given report assessment content:
      | title                | state | created              | organization      | author       |
      | Moderatable Report 1 | draft | 2020-01-01T00:00:00Z | Test Organization | Regular User |

    When I am logged in as "Moderator User"
    And I go to the moderate form of the "Moderatable Report 1" assessment report
    And I select the radio button "Published"
    And I press "Save"
    Then I should see the success message containing "The assessment report has been published"

    # Verify the report is now visible.
    Given I am an anonymous user
    When I go to "/collection/assessments/assessment-reports"
    Then I should see "Moderatable Report 1"

  Scenario: Moderator requests updates on draft assessment report
    Given report assessment content:
      | title                | state | created              | organization      | author       |
      | Moderatable Report 2 | draft | 2020-01-01T00:00:00Z | Test Organization | Regular User |

    When I am logged in as "Moderator User"
    And I go to the moderate form of the "Moderatable Report 2" assessment report
    And I select the radio button "Draft"
    And I fill in "What updates are needed?" with "Please add more details to the assessment."
    And I press "Save"
    Then I should see the success message containing "The submitter has been notified"

    # Verify the report remains in draft state.
    When I go to "/collection/assessments/assessment-reports"
    Then I should not see "Moderatable Report 2"
