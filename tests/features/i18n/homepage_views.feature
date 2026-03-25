@api @group-f
Feature: Test translations in the homepage.

  Scenario: Homepage topics are translatable.
    Given "el" translation for the "taxonomy_term" entity with label "Data gathering and processing":
      | field | value                    |
      | name  | Data gathering but Greek |
    And the following topics are enabled in the homepage:
      | Data gathering and processing |
    And "el" translation for the "meta_entity" entity with label "Data gathering and processing":
      | field       | value                         |
      | description | Only Greek have a description |

    When I go to the homepage
    Then I should see "Data gathering and processing"
    And I should not see "Only Greek have a description"
    # Checking the internal page verifies that the topic meta entity view also
    # shows the correct translated fields.
    When I click "Data gathering and processing"
    Then I should not see "Only Greek have a description"

    When I go to "/el"
    Then I should see "Data gathering but Greek"
    And I should see "Only Greek have a description"
    When I click "Data gathering but Greek"
    Then I should see "Only Greek have a description"

    # Test that the translated meta entities do not produce duplicates in
    # the administration screen.
    Given I am logged in as a moderator
    And I go to "/admin/homepage-topics"
    Then I should see "Data gathering and processing"
    But I should not see "Data gathering but Greek"
