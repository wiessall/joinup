@api @group-g
Feature: Asset release "delete" functionality.
  In order to manage releases
  As a solution facilitator
  I need to be able to delete "Release" rdf entities through UI.

  Background:
    Given the following "collection" content:
      | title                  |
      | Yet another collection |
    And the following "solution" content:
      | title                | description                | resources | collection             |
      | Yet another solution | Bored of finding new texts | text.pdf  | Yet another collection |
    And the following "release" content:
      | title               | release notes    | resources | release number | release notes   | is version of        |
      | Yet another release | A sample release | text.pdf  | 1              | Changed release | Yet another solution |
      | No release          | A sample release | text.pdf  | 1.2            | Changed release | Yet another solution |
      | Jinks               | A sample release | text.pdf  | 3              | Changed release | Yet another solution |
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
