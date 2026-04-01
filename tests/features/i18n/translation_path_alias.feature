@api @group-d @javascript
Feature: Translated group content should have proper path aliases.
  As a visitor of the site
  When I navigate to a translated group page or its subpages
  I should see a proper aliased URL instead of a node ID.

  Scenario: Translated collections and subpages have aliased URLs.
    Given collection content:
      | title                 | logo     |
      | Alias test collection | logo.png |
    And custom_page multilingual content:
      | title              | collection            | langcode | default_translation |
      | Alias test page    | Alias test collection | en       | 1                   |
      | Page de test alias |                       | fr       | 0                   |
    And I run the "joinup_i18n:translation_alias" queue worker

    When I go to the "Alias test collection" collection
    Then the url should match "/collection/alias-test-collection"
    When I visit the "Alias test page" "custom page"
    Then the url should match "/collection/alias-test-collection/alias-test-page"

    Given I am logged in as a moderator
    When I go to the homepage of the "Alias test collection" collection
    And I open the header local tasks menu
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "French" row
    And I fill in "Title" with "Collection de test alias"
    And I fill in "Abstract" with "Description courte"
    And I press "Save (all translations)"
    Then I should see the heading "Collection de test alias"
    And the url should match "/fr/collection/collection-de-test-alias"

    When I go to the "Alias test collection" collection
    Then the url should match "/collection/alias-test-collection"

    # Regenerate aliases so the custom page French alias uses the translated
    # title slug.
    And I run the "joinup_group:group_content_update" queue worker
    And I run the "joinup_i18n:translation_alias" queue worker

    When I go to "/fr/collection/alias-test-collection/page-de-test-alias"
    Then I should see the heading "Page de test alias"

    # The English-slug alias redirects to the translated one.
    When I go to "/fr/collection/alias-test-collection/alias-test-page"
    Then I should see the heading "Page de test alias"
    And the url should match "/fr/collection/alias-test-collection/page-de-test-alias"

    When I visit the "Alias test page" "custom page"
    And I press "EN" in the "Top Bar" region
    And I click "FR" in the "Popover" region
    Then the url should match "/fr/collection/alias-test-collection/page-de-test-alias"
    And I should see the heading "Page de test alias"
