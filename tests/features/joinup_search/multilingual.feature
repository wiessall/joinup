@api @group-g
Feature: Multilingual search
  As a user of the site I should only see English content

  Scenario: Anonymous user sees English version only
    Given collection content:
      | title                        | logo     | moderation | topic      | spatial coverage |
      | Molecular cooking collection | logo.png | no         | Demography | Belgium          |
    And solution multilingual content:
      | title                 | description           | collection                   | state     | langcode | default_translation |
      | El celler de Can Roca | English description   | Molecular cooking collection | published | en       | 1                   |
      |                       | Deutsche Beschreibung |                              |           | de       | 0                   |

    When I go to the "Molecular cooking collection" collection
    Then I should see 1 tile
