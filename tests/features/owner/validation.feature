@api @group-h
Feature: Validation of owners created through UI
  In order to ensure that my data is complete
  As a collection owner
  I need to prevent incomplete owners being created

  Scenario: Owner fields are required
    Given I am logged in as a user with the "authenticated" role
    And I go to the propose collection form
    When I press "Add new" at the "Owner" field
    And I press "Create owner"
    Then I should see the following error messages:
      | error messages             |
      | Name field is required.    |
      | Type field is required.    |
      | Country field is required. |
    And I fill in "Name" with "Leandro Keen"
    And I check "Company"
    And I fill in "Country" with "Belgium"
    And I press "Create owner"
    Then the "Owner IEF" table should contain the following columns:
      | Title        | Type    | Country | Status    |
      | Leandro Keen | Company | Belgium | Published |
    And I should not see the error message "Name field is required."
