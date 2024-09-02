@api @group-g
Feature: Multilingual search
  As a user of the site I should only see English content

  Scenario: Anonymous user sees English version only
    Given the language "ca" is enabled
    And collection content:
      | title                        | logo     | moderation | topic      | spatial coverage | state     |
      | Molecular cooking collection | logo.png | no         | Demography | Belgium          | published |
    And the multilingual "El celler de Can Roca" solution of "Molecular cooking collection" collection
    When I go to the "Molecular cooking collection" collection
    Then I should see 1 tile
