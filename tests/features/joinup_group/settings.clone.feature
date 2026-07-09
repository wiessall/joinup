@api @group-clone
Feature: Collections/Solutions settings - group clone tests

  Scenario: Regression test for 403 page title not shown to authenticated user on collection settings.
    Given I am logged in as a authenticated
    And I check the box "I agree to the Legal notice document"
    And I press "Submit"
    When I go to the canonical path of the "collection_settings" meta entity of the "Portal support" collection_settings "content"
    Then I should get an access denied error
    And I should see the heading "Access denied"