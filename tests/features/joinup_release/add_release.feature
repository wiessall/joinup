@api
Feature: "Add release" visibility options.
  In order to manage releases
  As a solution facilitator
  I need to be able to add "Release" rdf entities through UI.

  Scenario: "Add release" button should only be shown to solution facilitators.
    Given the following solution:
      | title         | Release solution test |
      | description   | My awesome solution   |
      | documentation | text.pdf              |
      | state         | published             |

    When I am logged in as a "facilitator" of the "Release solution test" solution
    And I go to the homepage of the "Release solution test" solution
    # The user has to press the '+' button for the option "Add release" to be
    # visible.
    Then I should see the link "Add release"

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Release solution test" solution
    Then I should not see the link "Add release"

    When I am an anonymous user
    And I go to the homepage of the "Release solution test" solution
    Then I should not see the link "Add release"

  Scenario: Add release as a solution facilitator.
    Given owner content:
      | name                 | owner type |
      | Organisation example | Company    |
    And the following solutions:
      | title          | description        | documentation | owner                | state     |
      | Release Test 1 | test description 1 | text.pdf      | Organisation example | published |
    And release content:
      | title           | is version of  | release number | status    | state     | creation date       | published at        |
      | Chasing shadows | Release Test 1 | 1.0            | Completed | published | 2014-08-30 23:59:00 | 2014-08-30 23:59:00 |
    Then I should have 1 release

    # Assert that the "Release" tiles are present and contain version, release date, label and bundle type.
    When I go to the homepage of the "Release Test 1" solution
    Then I should see the "Chasing shadows" tile
    And I should see the text "v1.0" in the "Chasing shadows" tile
    And I should see the text "Release date: 30/08/2014" in the "Chasing shadows" tile
    And I should see the statistics "Completed" in the "Chasing shadows" tile
    And I should see the text "Release" in the "Chasing shadows" tile

    # Check that the release should have a unique combination of title and
    # version number.
    When I am logged in as a "facilitator" of the "Release Test 1" solution
    When I go to the homepage of the "Release Test 1" solution
    And I click "Add release"
    Then I should see the heading "Add Release"
    # @todo Probably these are extra fields we need to keep. Revisit this when
    #   all changes are done.
    And the following fields should be present "Name, Release version, Release notes, Upload a new file or enter a URL, Geographical coverage, Search keywords, Status, Language, Date, Time"
    # The entity is new, so the current workflow state should not be shown.
    # @todo Remove this when the revision reference to solutions is implemented.
    #   Revisit this when all changes are done.
    And the following fields should not be present "Description, Logo, Solution type, Contact information, Included asset, Translation, Distribution, Current workflow state, Langcode, Motivation"

    When I press "Publish"
    Then I should see the following error messages:
      | error messages                     |
      | Name field is required.            |
      | Release version field is required. |

    When I fill in "Name" with "Chasing shadows"
    And I press "Publish"
    Then I should see the error message "Release version field is required."

    When I fill in "Release version" with "1.0"
    And I fill in "Release notes" with "Changed release."
    # Ensure that the Status field is a dropdown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3342
    And I select "Completed" from "Status"
    And I press "Publish"
    Then I should see the error message "A release with title Chasing shadows and version 1.0 already exists in this solution. Please choose a different title or version."

    # It should be possible to choose a different version number.
    And I fill in "Release version" with "1.1"
    And I press "Publish"
    Then I should have 2 releases

    # Verify that the "Chasing shadows 1.1." release is registered as a release
    # of the "Release Test 1" solution.
    When I go to the homepage of the "Release Test 1" solution
    Then I should not see the text "Download releases"
    When I click "Release history"
    Then I should see the text "Chasing shadows"
    And I should see the text "1.1"

    # It should be possible to create a release with the same version number but
    # a different title.
    When I go to the homepage of the "Release Test 1" solution
    And I click "Add release"
    When I fill in "Name" with "Chasing flares"
    And I fill in "Release version" with "1.0"
    And I press "Publish"
    Then I should have 3 releases

    # Clean up entities created through the UI.
    Then I delete the "Chasing shadows" release
    And I delete the "Chasing shadows" release
    And I delete the "Chasing flares" release

  Scenario: Do not allow access to the page if the parent is not a solution.
    Given the following collection:
      | uri   | http://example1regression |
      | title | The Stripped Stream       |
      | state | published                 |

    When I am not logged in
    And I go to "/rdf_entity/http_e_f_fexample1regression/release/add"
    Then I should see the heading "Page not found"

    When I am logged in as a moderator
    And I go to "/rdf_entity/http_e_f_fexample1regression/release/add"
    Then I should see the heading "Page not found"

  Scenario: Check publish time release is present time and past.
    Given owner content:
      | name                     | owner type |
      | Organisation example two | Company    |
    And the following solutions:
      | title          | description        | documentation | owner                    | state     |
      | Release Test 2 | test description 2 | text.pdf      | Organisation example two | published |
    When I am logged in as a "facilitator" of the "Release Test 2" solution
    And I go to the homepage of the "Release Test 2" solution
    And I click "Add release" in the plus button menu
    Then I should see the heading "Add Release"
    And I should see the workflow buttons "Save as draft, Publish"
    And I fill in "Name" with "Release of the dark ship1"
    And I fill in "Release version" with "1"
    And I fill in "Authored on" with the date "+2 day"
    And I press "Publish"
    Then I should see the error message 'Your "Authored on" field date is greater than'
