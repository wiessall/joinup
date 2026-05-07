@api @group-g
Feature: "Edit" visibility options.
  In order to manage releases
  As a solution facilitator
  I need to be able to edit "Release" rdf entities through UI.

  Scenario: "Edit" button should only be shown to solution facilitators and moderators.
    Given the following "solution" content:
      | title                   | description         | resources |
      | My awesome solution abc | My awesome solution | text.pdf  |
    And the following "release" content:
      | title                      | release notes | resources | release number | release notes | is version of           | creation date    | modification date | published at     |
      | My awesome solution abc v1 | A sample      | text.pdf  | 1              | Changed       | My awesome solution abc | 2020-11-19 20:45 | 2022-11-19 20:45  | 2020-11-19 20:45 |

    When I am logged in as a "facilitator" of the "My awesome solution abc" solution
    And I go to the homepage of the "My awesome solution abc v1" release
    Then I should see the link "Edit" in the "Entity actions" region

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "My awesome solution abc v1" release
    Then I should not see the link "Edit" in the "Entity actions" region

    When I am an anonymous user
    And I go to the homepage of the "My awesome solution abc v1" release
    Then I should not see the link "Edit" in the "Entity actions" region
    And I should see the text "Published on: 19/11/2020"
    And I should see the text "Last update: 19/11/2022"
