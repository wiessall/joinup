@api @group-d
Feature: Solutions by licence report
  As a moderator of the site
  In order to be able to manage licences of the website
  I need to be able to list the licences and their related solutions.

  Scenario: Only privileged users can access the report.
    When I am logged in as a user with the "authenticated" role
    And I go to "/admin/reporting/solutions-by-licences"
    Then the response status code should be 403

    When I am logged in as a moderator
    And I am on the homepage
    And I click "Reporting" in the "Administration toolbar" region
    And I click "Solutions by licences"
    Then the response status code should be 200

  Scenario: List licences and their solutions.
    Given licence content:
      | title          |
      | Open source    |
      | Free for all   |
      | Commercial     |
      | Not applicable |
    And the following solutions:
      | title                 | state     |
      | Solution by licence 1 | published |
      | Solution by licence 2 | published |
    And release content:
      | title                         | release number | is version of         | state     |
      | Solution by licence 2 release | 1              | Solution by licence 2 | published |
    # Distribution linked to the solution directly.
    And distribution content:
      | title                     | licence      | solution              | state     |
      | Solution_by_licence_1.tar | Open source  | Solution by licence 1 | published |
      | Solution_by_licence_1.exe | Free for all | Solution by licence 1 | published |
    # Distribution that belongs to the release of a solution.
    And distribution content:
      | title                     | licence    | release                       | state     |
      | Solution_by_licence_2.tar | Commercial | Solution by licence 2 release | published |

    When I am logged in as a moderator
    And I click "Reporting" in the "Administration toolbar" region
    And I click "Solutions by licences"
    Then I should see the following links:
      | Open source           |
      | Free for all          |
      | Commercial            |
      | Solution by licence 1 |
      | Solution by licence 2 |
    # Licences without a solution related to them are not shown.
    And I should not see the following links:
      | Not applicable |

    # Test the filtering.
    When I select "Open source" from "Licence"
    And I press "Filter"
    Then I should see the following links:
      | Open source           |
      | Solution by licence 1 |
    And I should not see the following links:
      | Free for all          |
      | Commercial            |
      | Not applicable        |
      | Solution by licence 2 |
    When I select "Not applicable" from "Licence"
    And I press "Filter"
    Then I should see the text "No solutions available"
