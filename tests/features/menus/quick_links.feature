@api @group-h
Feature: Test the visibility of the quick links menu.

  Background:
    Given collection content:
      | title           | content_creation         | group_moderation |
      | Some collection | facilitators and authors | yes              |

  Scenario: Test visibility for collections.
    When I am not logged in
    And I go to the "Some collection" collection
    Then I should not see the heading "Quick links"
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."
    And I should not see the link "Website link"

    Given I am logged in as a moderator
    And I go to the "Some collection" collection
    Then I should see the text "Quick links"
    And I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."
    But I should not see the link "Website link"

    # Edit the collection and set the website link.
    When I go to the edit form of the "Some collection" collection
    And I fill in the following:
      | Access URL | http://www.example.com |
    And I press "Publish"
    Then I should see the text "Quick links"
    And I should see the link "Website link"

    # Test that facilitators can see the contextual links
    Given I am logged in as a facilitator of the "Some collection" collection
    And I go to the "Some collection" collection
    Then I should see the text "Quick links"
    And I should see the contextual link "Edit menu" in the Content region

    When I am not logged in
    And I go to the "Some collection" collection
    Then I should see the text "Quick links"
    And I should not see the contextual link "Edit menu" in the Content region
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Unset the value, and ensure the link is not visible.
    When I am logged in as a moderator
    And I go to the edit form of the "Some collection" collection
    And I fill in the following:
      | Access URL |  |
    And I press "Publish"
    Then I should not see the heading "Quick links"
    And I should not see the link "Website link"

    When I am not logged in
    And I go to the "Some collection" collection
    Then I should not see the heading "Quick links"

  @javascript
  Scenario: Test visibility for solutions.
    Given collection content:
      | title           | content_creation         | group_moderation |
      | Some collection | facilitators and authors | yes              |
    Given solution content:
      | title         | collection      |
      | Some solution | Some collection |
    When I am not logged in
    And I go to the "Some solution" solution
    Then I should not see the heading "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given I am logged in as a moderator
    And I go to the "Some solution" solution
    Then I should not see the heading "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
    But I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Lets not test all links in a separate list of steps to save time.
    Given release content:
      | title        | is version of |
      | Some release | Some solution |
    And I go to the edit form of the "Some solution" solution
    And I click "Additional information"
    And I fill in the following:
      | Relevant website | http://www.example.com |
    And I press "Upload file"
    Then I attach the file "text.pdf" to "files[resources_0_subform_resource_file_0]"
    And I press "Confirm"
    And I press "Publish"
    Then I should see the text "Quick links"
    And I should see the following links:
      | Website link    |
      | Release history |
    But I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

     # Test that facilitators can see the contextual links
    Given I am logged in as a facilitator of the "Some solution" solution
    And I go to the "Some solution" solution
    Then I should see the text "Quick links"
    And I should see the contextual links button in the "Navigation menu"
    And I should see the contextual link "Edit menu" in the "Navigation menu" region

    When I am not logged in
    And I go to the "Some solution" solution
    Then I should see the text "Quick links"
    And I should see the following links:
      | Website link    |
      | Release history |
    And I should not see the contextual link "Edit menu" in the "Navigation menu" region

  @javascript
  Scenario: Test quick links menu structure.
    Given collection content:
      | title           |
      | Some collection |
    And custom_page content:
      | title         | collection      |
      | Custom page 1 | Some collection |
      | Custom page 2 | Some collection |
      | Custom page 3 | Some collection |
      | Custom page 4 | Some collection |
      | Custom page 5 | Some collection |
    And collection content:
      | title   | Custom page 1 | Custom page 2 | Custom page 3 | Custom page 4 | Custom page 5 |
      | enabled | yes           | yes           | yes           | yes           | yes           |
    When I am logged in as a facilitator of the "Some collection" collection
    And I go to the "Some collection" collection
    And I click the contextual link "Edit menu" in the "Quick links" region
    Then the draggable menu table of the "Some collection" collection should be:
      | title         |
      | Custom page 1 |
      | Custom page 2 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |

    When I drag the "Custom page 2" table row up
    Then the draggable menu table of the "Some collection" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |

    And I press "Save"
    Then I should see the text "Quick links"
    And I click the contextual link "Edit menu" in the "Quick links" region
    Then the draggable menu table of the "Some collection" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |

    # Nesting functionality should be disabled.
    When I drag the "Custom page 2" table row left
    When I drag the "Custom page 1" table row right
    Then the draggable menu table of the "Some collection" collection should be:
      | title         |
      | Custom page 2 |
      | Custom page 1 |
      | Custom page 3 |
      | Custom page 4 |
      | Custom page 5 |
