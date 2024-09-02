@api @group-h
Feature: Test the visibility of the quick links menu.

  Background:
    Given owner content:
      | name                 | owner type |
      | Organisation example | Company    |
    And contact_information content:
      | name                 | email                            |
      | Collection editorial | collection.editorial@example.com |
    And collection content:
      | title           | abstract      | description      | content creation         | moderation | topic             | owner                | contact information  | state     |
      | Some collection | Some abstract | Some description | facilitators and authors | yes        | Supplier exchange | Organisation example | Collection editorial | published |

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

  Scenario: Test visibility for solutions.
    Given solution content:
      | title         | abstract      | description      | topic             | solution type    | owner                | contact information  | state     |
      | Some solution | Some abstract | Some description | Supplier exchange | Digital Solution | Organisation example | Collection editorial | published |
    When I am not logged in
    And I go to the "Some solution" solution
    Then I should not see the heading "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
      | Documentation   |
    And I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    Given I am logged in as a moderator
    And I go to the "Some solution" solution
    Then I should not see the heading "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
      | Documentation   |
    But I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

    # Lets not test all links in a separate list of steps to save time.
    Given release content:
      | title        | is version of | state     |
      | Some release | Some solution | published |
    And I go to the edit form of the "Some solution" solution
    And I fill in the following:
      | Landing page | http://www.example.com |
    Then I upload the file "text.pdf" to "Upload a new file or enter a URL"
    And I press "Publish"
    Then I should see the text "Quick links"
    And I should see the following links:
      | Website link    |
      | Release history |
      | Documentation   |
    But I should not see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."

     # Test that facilitators can see the contextual links
    Given I am logged in as a facilitator of the "Some solution" solution
    And I go to the "Some solution" solution
    Then I should see the text "Quick links"
    And I should see the contextual link "Edit menu" in the Content region

    When I am not logged in
    And I go to the "Some solution" solution
    Then I should see the text "Quick links"
    And I should see the following links:
      | Website link    |
      | Release history |
      | Documentation   |
    And I should not see the contextual link "Edit menu" in the Content region

  @javascript
  Scenario: Test quick links menu structure.
    Given collection content:
      | title           | state     |
      | Some collection | published |
    And custom_page content:
      | title         | collection      | state     | topic |
      | Custom page 1 | Some collection | published | HR    |
      | Custom page 2 | Some collection | published | HR    |
      | Custom page 3 | Some collection | published | HR    |
      | Custom page 4 | Some collection | published | HR    |
      | Custom page 5 | Some collection | published | HR    |
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
