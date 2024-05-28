@api @group-g
Feature: Related solution
  As a solution facilitator, I need to be able to relate my solution to other
  solutions and present the related solutions to the users.

  Background:
    Given contact_information content:
      | name        | email       |
      | Kalikatoura | bar@bar.com |
    And owner content:
      | name         | owner type |
      | Kalikatoures | Company    |
    And solutions:
      | title      | related solutions | abstract                         | description                         | documentation | related by type | moderation | logo     | topic      | state     | solution type | owner        | contact information |
      | C          |                   | Blazing fast segmentation faults | Blazing fast segmentation faults.   | text.pdf      | yes             | no         | logo.png | Demography | published |               | Kalikatoures | Kalikatoura         |
      | Java       | C                 | Because inheritance is cool      | Because inheritance is cool.        | text.pdf      | yes             | no         | logo.png | Demography | published | Citizen       | Kalikatoures | Kalikatoura         |
      | PHP        |                   | Make a site                      | Make a site.                        | text.pdf      | yes             | yes        | logo.png | Demography | published | Citizen       | Kalikatoures | Kalikatoura         |
      | Golang     |                   | Concurrency for the masses       | Concurrency for the masses          | text.pdf      | yes             | yes        | logo.png | Demography | proposed  | Citizen       | Kalikatoures | Kalikatoura         |
      | Python     |                   | Get stuff done                   | Get stuff done.                     | text.pdf      | yes             | no         | logo.png | Demography | published |               | Kalikatoures | Kalikatoura         |
      | Javascript | Java, PHP         | Java is related to javascript    | Java is related to javascript. Huh? | text.pdf      | no              | no         | logo.png | Demography | published | Citizen       | Kalikatoures | Kalikatoura         |

  Scenario: Related solutions
    # Scenario A. A collection owner manages his own collection.
    When I visit the "Java" solution
    # Referenced through EIRA building block.
    Then I see the "PHP" tile
    And I should see the "Javascript" tile
    # Direct reference.
    And I see the "C" tile
    # Not referenced.
    And I should not see the "Python" tile
    # Golang is not published, and should not be shown
    And I should not see the "Golang" tile

    # Relate two solutions.
    When I am logged in as a facilitator of the "Java" solution
    And I visit the "Java" solution
    And I click "Edit" in the "Entity actions" region
    And I fill in "Related solutions" with values "C, Python"
    And I uncheck "Show solutions related by EIRA terms"
    And I press "Propose"
    Then I should see the heading "Java"
    # The "Java" solution is not published yet.
    And I should not see the "Python" tile
    # "C" is still directly referenced.
    And I should see the "C" tile
    But I should see the "Javascript" tile
    And I should see the "PHP" tile

    # Test that checking the eira related checkbox will make the tiles available again.
    When I am logged in as a moderator
    And I visit the "Java" solution
    When I click "Edit" in the "Entity actions" region
    And I check "Show solutions related by EIRA terms"
    And I press "Publish"
    Then I should see the heading "Java"
    And I should see the "Python" tile
    And I should see the "Javascript" tile
    And I should see the "PHP" tile

  @javascript
  Scenario: Moderators can reorder the related solutions.
    Given I am logged in as a moderator

    # Solutions that have 'Solution related by type' off, should not show solutions related by type.
    When I visit the "Javascript" solution
    Then I should see the following tiles in the correct order:
      | Java |
      | PHP  |

    When I go to the edit form of the "Javascript" solution
    And I expand the "Additional fields" accordion section
    And I drag the table row in the "Related solutions" region at position 2 up
    And I press "Publish"
    Then I should see the heading "Javascript"
    And I should see the following tiles in the correct order:
      | PHP  |
      | Java |
