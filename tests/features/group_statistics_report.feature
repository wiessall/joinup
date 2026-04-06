@group-clone @api
Feature: Minimal install clone testing.

  Scenario: Regression test for "Reporting Statistics per group".
    Given I am logged in as a user with the moderator role
    When I am on "/admin/reporting/statistics-per-group"
    Then I should see the heading "Statistics per group"
    And I press "Generate data"
    Then I should see the heading "Exporting statistics per group"
    And I wait for the batch process to finish
    Then the response status code should be 200
    And I should see the success message "Data has been rebuilt."
