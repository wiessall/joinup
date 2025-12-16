@api @group-c
Feature: Assessment report email notifications
  Tests email notifications sent during assessment report moderation workflow.

  Background:
    Given users:
      | Username       | Roles               | E-mail                | First name | Family name |
      | Regular User   |                     | regular@example.com   | Regular    | User        |
      | Reporter User  | assessment_reporter | reporter@example.com  | Reporter   | User        |
      | Moderator User | moderator           | moderator@example.com | Moderator  | User        |
    And public_organisation assessments:
      | name              | organisation_type          |
      | Test Organization | european_union_institution |
    And expression assessments:
      | name            | description                 |
      | Test Expression | Test expression description |
    And public_service assessments:
      | name         |
      | Test Service |

  Scenario: Email notification: Your assessment report has been published
    Given report assessments:
      | name                 | state  | provider          | uid          |
      | Moderatable Report 1 | review | Test Organization | Regular User |
    And the following report has binding requirement:
      | report     | Moderatable Report 1 |
      | expression | Test Expression      |
      | service    | Test Service         |
    And I mark all emails as read

    When I am logged in as "Moderator User"
    And I go to the moderate form of the "Moderatable Report 1" assessment report
    Then I see the text "Interoperability assessments space" in the "Header" region
    And I should see the following lines of text:
      | Provided by Test Organization, a European Union institution           |
      | Status: In Review                                                     |
      | The assessment report was based on the following binding requirements |
    When I press "Publish"
    Then I should see the success message containing "The assessment report has been published"

    And 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Regular User                                                             |
      | subject   | Your assessment report has been published                                |
      | body      | Your submitted assessment report has been approved and is now published. |

  Scenario: Email notification: New assessment report to be reviewed
    Given report assessments:
      | name                 | state | provider          | uid          |
      | Moderatable Report 2 | draft | Test Organization | Regular User |
    And the following report has binding requirement:
      | report     | Moderatable Report 2 |
      | expression | Test Expression      |
      | service    | Test Service         |
    And I mark all emails as read

    When I am logged in as "Regular User"
    And I visit the "Moderatable Report 2" assessment
    And I click "Edit" in the "Entity actions" region
    And I press "Save as In Review"
    Then I should see the success message containing "has been updated"

    And 2 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Moderator User                             |
      | subject   | New assessment report to be reviewed       |
      | body      | A new assessment report has been submitted |

  Scenario: Email notification: Your assessment report needs updates
    Given report assessments:
      | name                 | state  | provider          | uid          |
      | Moderatable Report 3 | review | Test Organization | Regular User |
    And the following report has binding requirement:
      | report     | Moderatable Report 3 |
      | expression | Test Expression      |
      | service    | Test Service         |
    And I mark all emails as read

    When I am logged in as "Moderator User"
    And I go to the moderate form of the "Moderatable Report 3" assessment report
    And I press "Require updates"
    Then I should see the error message "When requesting updates, you must provide a reason."
    When I fill in "What updates are needed?" with "Please add more details to the assessment."
    And I press "Require updates"
    Then I should see the success message containing "The submitter has been notified"

    And 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient | Regular User                               |
      | subject   | Your assessment report needs updates       |
      | body      | Please add more details to the assessment. |
