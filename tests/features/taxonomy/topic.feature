@api @group-d
Feature:
  As a moderator
  When I go to the overview page of topic meta entity type
  I want to be able to see Topic Meta Entity page.
  And Check a given parent is displayed in a 2nd tier term row.
  And Check that certain topic is not published then we edit term and we publish when we going back to the table then term is published.
  @terms
  Scenario: Show list of topics meta entity type and check a given parent and published field.
    When I am logged in as a moderator
    And I go to "/dashboard"
    Then I should see the link Topics
    When I click Topics
    Then I should see the heading "Topics"
    And I should see "eGov" in the "Supplier exchange" row
    And I should see "Economy and Welfare" in the "Finance in EU" row
    And I should see "E-health Dpt." in the "E-health" row
    And I should see "HR Dpt." in the "Employment and Support Allowance" row
    And I should see "HR Dpt." in the "HR" row
    When I click "Edit" in the "E-justice" row
    Then I should see the heading "Edit E-justice"
    And the checkbox "edit-published-value" should not be checked
    When I check "edit-published-value"
    And I press "edit-submit"
    When I visit "admin/topics"
    Then I should see "Yes" in the "E-justice" row
