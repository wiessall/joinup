@api @group-g
Feature: Publication date works as expected on RDF entities

  Background:
    Given the following "collection" content:
      | title                 | state     |
      | Collection to publish | draft     |
      | Published collection  | published |
    Given the following "solution" content:
      | title               | state     | collection           |
      | Solution to publish | draft     | Published collection |
      | Published solution  | published | Published collection |
    And the following "release" content:
      | title              | state | release number | release notes              | is version of      |
      | Release to publish | draft | 1.0.0          | Only works on flat ground. | Published solution |

  Scenario Outline: Publication date works as expected on precreated draft groups
    Given I am logged in as a moderator
    When I go to the edit form of the "<entity label>" <type>
    Then I should see the heading "Edit <type label> <entity label>"
    When I fill in "<label field>" with "Published at test for <type>"
    And I press "Publish"
    # Posting a release redirects to the solution canonical, so we verify the
    # success of the entity edit by checking whether the new title was saved.
    And I go to the homepage of the "Published at test for <type>" <type>
    Then the published_at field in "Published at test for <type>" <type> should not be empty

    Examples:
      | entity label          | type       | type label | label field     |
      | Collection to publish | collection | Collection | title[0][value] |
      | Release to publish    | release    | Release    | title[0][value] |

  Scenario: Publication date works as expected on solution
    Given I am logged in as a moderator
    When I go to the edit form of the "Solution to publish" solution
    Then I should see the heading "Edit Solution Solution to publish"
    When I fill in "Title" with "Published at test for solution"
    And I press "Confirm"
    And I press "Publish"
    And I go to the homepage of the "Published at test for solution" solution
    Then the published_at field in "Published at test for solution" solution should not be empty

  Scenario: Publication date works as expected on distributions
    Given the following "licence" content:
      | title   | description | deprecated |
      | Licence | Description | no         |
    When I am logged in as a "facilitator" of the "Published solution" solution
    And I go to the homepage of the "Published solution" solution
    When I click "Add distribution"
    Then I should see the heading "Add Distribution"
    When I fill in "Title" with "Distribution published at test"
    And I select "Licence" from "Licence"
    And I attach the file "test.zip" to "files[resources_0_subform_resource_file_0]"
    And I press "Publish"
    Then I should see the heading "Distribution published at test"
    And the published_at field in "Distribution published at test" distribution should not be empty
