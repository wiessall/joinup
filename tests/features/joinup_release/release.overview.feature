@api
Feature: Asset distribution overview on solution.
  In order to view an overview of a solution's releases and download them
  As a user of the website
  I need to be able to view the releases of a solution.

  Scenario: Releases should be available in the overview page.
    Given the following collection:
      | title | End of Past |
      | state | published   |
    And the following solutions:
      | title            | collection  | description        | state     |
      | Lovely Butterfly | End of Past | Sample description | published |

    # A solution with no releases or standalone distributions has no button.
    When I go to the homepage of the "Lovely Butterfly" solution
    Then I should not see the link "Release history"
    When I go to "/collection/end-past/solution/lovely-butterfly/releases"
    Then I should see the heading "Sign in to continue"

    # The release numbers do not follow the creation date to ensure proper
    # ordering. "The Child of the Past" should be shown first as it is the
    # latest release created, even though it is not the latest in the version
    # number.
    And release content:
      | title                 | documentation | release number | release notes | creation date    | published at     | is version of    | state     |
      | Hidden spies          | text.pdf      | 3              | Notes 3       | 28-01-1995 12:05 | 28-01-1995 12:05 | Lovely Butterfly | draft     |
      | Thief in the Angels   | text.pdf      | 2              | Notes 2       | 28-01-1995 12:06 | 29-01-1995 12:06 | Lovely Butterfly | published |
      | The Child of the Past | text.pdf      | 1              | Notes 1       | 29-01-1996 12:05 | 29-01-1996 12:05 | Lovely Butterfly | published |
    And distribution content:
      | title       | access url                          | creation date    | parent                | state     |
      | Linux       | test.zip                            | 28-01-1995 12:05 | Thief in the Angels   | published |
      | Windows     | http://www.example.org/download.php | 28-01-1995 12:06 | The Child of the Past | published |
      | User manual | test.zip                            | 28-01-1995 11:07 | Lovely Butterfly      | published |
      | Solaris     | test.zip                            | 28-01-1995 12:08 | Hidden spies          | published |

    When I go to the homepage of the "Lovely Butterfly" solution
    And I click "Release history"
    Then I should see the heading "Releases for Lovely Butterfly solution"
    And the page should be cacheable

    # Check that clean URLs are being applied to the "releases" subpage.
    And I should be on "/collection/end-past/solution/lovely-butterfly/releases"
    And I should see the heading "Releases for Lovely Butterfly solution" in the "Page title" region

    # Only the published releases should be shown.
    # The release titles include the version as a suffix.
    And I should see the following releases in the exact order:
      | release                 |
      | The Child of the Past 1 |
      | Thief in the Angels 2   |
    # The standalone distribution should also be visible.
    And I should see the link "User manual"
    And I should see the text "Standalone distribution"
    # Distributions of unpublished releases should not be shown.
    But I should not see the text "Solaris"

    And I should see the download link in the "Linux" distribution
    And I should see the download link in the "User manual" distribution
    And I should see the external link in the "Windows" distribution

    And the "The Child of the Past" release should be marked as the latest release

    # Verify that the releases titles link to the release page.
    And I should see the link "The Child of the Past 1"
    And I should see the text "Published on: 29/01/1996"
    And I should see the link "Thief in the Angels 2"
    And I should see the text "Published on: 29/01/1995"
    When I click "The Child of the Past 1"
    Then I should see the text "The Child of the Past 1" in the "Page title" region
    And I should see the text "Latest release"
    And the page should be cacheable

    # Create a new release.
    When I am logged in as a facilitator of the "Lovely Butterfly" solution
    And I go to the homepage of the "Lovely Butterfly" solution
    And I click "Add release" in the plus button menu
    Then I should see the heading "Add Release"
    When I fill in "Name" with "The Deep Doors"
    And I fill in "Release version" with "4"
    And I enter "Notes 4" in the "Release notes" wysiwyg editor

    # Regression test. First save as draft and then publish to ensure
    # proper cache invalidation.
    # @see: ISAICP-3128
    And I press "Save as draft"
    Then I should see the text "The Deep Doors 4" in the "Page title" region
    When I am an anonymous user
    And I go to the homepage of the "Lovely Butterfly" solution
    And I click "Release history"
    Then I should see the following releases in the exact order:
      | release                 |
      | The Child of the Past 1 |
      | Thief in the Angels 2   |
    And the "The Child of the Past" release should be marked as the latest release
    When I click "Thief in the Angels 2"
    Then I should see the text "Thief in the Angels 2" in the "Page title" region
    When I click "Switch to the latest release"
    Then I should see the text "The Child of the Past 1" in the "Page title" region
    And I should see the text "Latest release"

    # Publish the release as a solution facilitator.
    When I am logged in as a facilitator of the "Lovely Butterfly" solution
    And I go to the homepage of the "The Deep Doors" release
    When I click "Edit" in the "Entity actions" region
    And I press "Publish"
    Then I should see the text "The Deep Doors 4" in the "Page title" region

    # We need to logout as unpublished releases are also shown in the list for
    # privileged users.
    # @see ISAICP-3393
    When I am an anonymous user
    And I go to the homepage of the "Lovely Butterfly" solution
    And I click "Release history"
    Then I should see the following releases in the exact order:
      | release                 |
      | The Deep Doors 4        |
      | The Child of the Past 1 |
      | Thief in the Angels 2   |
    And the "The Deep Doors" release should be marked as the latest release

    # Check that the cache is invalidated.
    When I delete the "Linux" distribution
    And I delete the "Windows" distribution
    And I delete the "User manual" distribution
    And I delete the "Solaris" distribution
    And I delete the "Hidden spies" release
    And I delete the "Thief in the Angels" release
    And I delete the "The Child of the Past" release
    And I delete the "The Deep Doors" release
    When I go to the homepage of the "Lovely Butterfly" solution
    Then I should not see the link "Release history"
    When I go to "/collection/end-past/solution/lovely-butterfly/releases"
    Then I should see the text "Sign in to continue"
