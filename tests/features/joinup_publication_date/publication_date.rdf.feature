@api @group-i
Feature: Publication date works as expected on RDF entities

  Background:
    Given owner content:
      | name  |
      | Owner |
    And contact_information content:
      | name     |
      | Contact1 |
      | Contact2 |
      | Contact3 |
      | Contact4 |
    And the following collections:
      | title                 | state     | description | owner | contact information |
      | Collection to publish | draft     | Description | Owner | Contact1            |
      | Published collection  | published | Description | Owner | Contact2            |
    And solutions:
      | title               | state     | collection           | solution type | abstract | description | moderation | owner | contact information |
      | Solution to publish | draft     | Published collection | Business      | Abstract | Description | no         | Owner | Contact3            |
      | Published solution  | published | Published collection | Business      | Abstract | Description | no         | Owner | Contact4            |
    And release content:
      | title              | state | release number | release notes              | is version of      |
      | Release to publish | draft | 1.0.0          | Only works on flat ground. | Published solution |

  Scenario Outline: Publication date works as expected on precreated draft RDF entities
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
      | Collection to publish | collection | Collection | label[0][value] |
      | Solution to publish   | solution   | Solution   | label[0][value] |
      | Release to publish    | release    | Release    | title[0][value] |

  Scenario: Publication date works as expected on distributions
    Given licence content:
      | title   | description | deprecated |
      | Licence | Description | no         |
    When I am logged in as a "facilitator" of the "Published solution" solution
    And I go to the homepage of the "Published solution" solution
    When I click "Add distribution"
    Then I should see the heading "Add Distribution"
    When I fill in "Title" with "Distribution published at test"
    And I select "Licence" from "Licence"
    And I press "Publish"
    Then I should see the heading "Distribution published at test"
    And the published_at field in "Distribution published at test" distribution should not be empty
