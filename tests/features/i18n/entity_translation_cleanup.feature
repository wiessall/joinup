@api
Feature: Reverting translations added to pre-existing entities
  In order to keep Behat scenarios isolated from one another
  As a developer
  I need translations added to seeded entities to be removed after each scenario

  # Scenario order matters: the second scenario asserts that the first one's
  # AfterScenario cleanup reverted the translation.

  Scenario: A translation can be added to a pre-existing entity
    Given fr translation for the taxonomy_term entity with label "Belgium":
      | field | value    |
      | name  | Belgique |
    Then the taxonomy_term entity with label "Belgium" should have a fr translation

  Scenario: The translation added to a pre-existing entity is reverted afterwards
    Then the taxonomy_term entity with label "Belgium" should not have a fr translation
