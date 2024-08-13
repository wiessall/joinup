@api @group-f
Feature: Asset release "delete" functionality.
  In order to manage releases
  As a solution facilitator
  I need to be able to delete "Release" rdf entities through UI.

  Background:
    Given owner content:
      | name              |
      | Yet another owner |
    And contact_information content:
      | name                | email                         | website url        |
      | Yet another contact | yetanothercontact@example.com | http://example.com |
    And the following collection:
      | title | Yet another collection |
      | state | published              |
    And the following solution:
      | title               | Yet another solution       |
      | description         | Bored of finding new texts |
      | documentation       | text.pdf                   |
      | owner               | Yet another owner          |
      | contact information | Yet another contact        |
      | collection          | Yet another collection     |
      | state               | published                  |
    And release content:
      | title               | release notes    | documentation | release number | release notes   | is version of        | owner             | contact information | state     |
      | Yet another release | A sample release | text.pdf      | 1              | Changed release | Yet another solution | Yet another owner | Yet another contact | published |
      | No release          | A sample release | text.pdf      | 1.2            | Changed release | Yet another solution | Yet another owner | Yet another contact | published |
      | Jinks               | A sample release | text.pdf      | 3              | Changed release | Yet another solution | Yet another owner | Yet another contact | published |
    And users:
      | Username        | E-mail                      | First name | Family name |
      | Ronnie Holloway | Ronnie.holloway@example.com | Ronnie     | Holloway    |
      | Felix Rusevelt  | Felix.Rusevelt@example.com  | Felix      | Rusevelt    |
      | Nuno Mendes     | Nuno.Mendes@example.com     | Nuno       | Mendes      |
    And the following solution user membership:
      | solution             | user            | roles       |
      | Yet another solution | Ronnie Holloway | owner       |
      | Yet another solution | Felix Rusevelt  | facilitator |
      | Yet another solution | Nuno Mendes     |             |

  Scenario: "Delete" action should redirect users to the parent solution.
    # Navigating through UI should redirect the user to the solution homepage.
    When I am logged in as a "moderator"
    And I go to the edit form of the "Yet another release" release
    And I click "Delete"
    And I press "Delete"
    Then I should be on "/collection/yet-another-collection/solution/yet-another-solution"

  Scenario: Allow solution owners to "delete" their own releases.
    When I am logged in as "Ronnie Holloway"
    And I go to the edit form of the "Yet another release" release
    And I should see the link "Delete"

    When I am logged in as "Felix Rusevelt"
    And I go to the edit form of the "Yet another release" release
    And I should not see the link "Delete"
