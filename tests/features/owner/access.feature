@api @group-h
Feature: Owner access
  In order to see contextualized information
  As a visitor
  I don't want to see the owner full page.

  Scenario: Anonymous user contact information access
    Given owner content:
      | name          | owner type |
      | Daisy Johnson | Company    |

    When I am an anonymous user
    When I go to the "Daisy Johnson" owner
    Then I should see the heading "Sign in to continue"
