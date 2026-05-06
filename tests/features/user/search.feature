@api @group-i
Feature: User management
  As a moderator I must be able to search users.

  Scenario: Moderator can search for users
    # Trusty user created at 05/01/2014
    # Newkid user created at 03/05/2016
    # Old guy user was created at 06/05/2012
    Given the following users:
      | Username           | E-mail                   | Roles         | Created    | Status  |
      | Jolly user manager | jolly.bambo@example.com  | Moderator     |            | active  |
      | Trusty user        | trusty.bambo@example.com |               | 1388880000 | blocked |
      | Newkid             | newkid.bambo@example.com |               | 1457136000 | active  |
      | Old guy            | old.bambo@example.com    | Administrator | 1336262400 | active  |
    Given I am logged in as "Jolly user manager"
    When I am on the homepage
    Then I click "People"
    Then I should be on "admin/people"
    Then I should see the text "Trusty user"
    And I should see the text "trusty.bambo@example.com"
    Then I should see the text "Newkid"
    And I should see the text "newkid.bambo@example.com"

    # Filter on date
    Then I fill in "Created after" with "1-1-2014"
    And I fill in "Created before" with "10-1-2014"
    And I press "Filter"
    Then I should see the text "Trusty user"
    Then I should not see the text "Newkid"
    Then I press "Reset"

    # Filter on blocked
    Then I select "Active" from "Status"
    And I press "Filter"
    Then I should see the text "Newkid"
    Then I should not see the text "Trusty user"
    Then I press "Reset"

    # Filter by role
    Then I select "Administrator" from "Role"
    And I press "Filter"
    Then I should see the text "Old guy"
    But I should not see the text "Trusty user"
    And I should not see the text "Newkid"
    Then I press "Reset"
