@api @group-i
Feature: Test for a 'facets' regression on the solution overview page.
  # @see ISAICP-4188
  Scenario: Test that no pager is shown on the solution page when not needed.
    Given solution content:
      | title                |
      | Apache               |
      | Security audit tools |
      | Drupal               |
      | MongoDB              |
      | NodeJS               |
      | Solution 1           |
      | Solution 2           |
      | Solution 3           |
      | Solution 4           |
      | Solution 5           |
      | Solution 6           |
      | Solution 7           |
      | Solution 8           |
      | Solution 9           |
      | Solution 10          |
    And distribution content:
      | title    | resources  | solution |
      | test.zip | test.zip   | Apache   |
    And I go to the homepage of the "Apache" solution
    Then I should not see the "Pager" region
