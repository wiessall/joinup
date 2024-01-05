@api @terms @group-i
Feature: Test the visibility of the quick links menu.

  Background:
    Given the following owner:
      | name                 | type                    |
      | Organisation example | Non-Profit Organisation |
    And the following contact:
      | name  | Collection editorial             |
      | email | collection.editorial@example.com |
    And collection:
      | title               | Some collection          |
      | abstract            | Some abstract            |
      | description         | Some description         |
      | content creation    | facilitators and authors |
      | moderation          | yes                      |
      | topic               | Supplier exchange        |
      | owner               | Organisation example     |
      | contact information | Collection editorial     |
      | state               | published                |

  Scenario: Test visibility for collections.
    When I am not logged in
    And I go to the "Some collection" collection
    Then I should not see the text "Quick links"
    And I should not see the link "Website link"

    Given I am logged in as a moderator
    And I go to the "Some collection" collection
    Then I should not see the text "Quick links"
    And I should not see the link "Website link"

    # Edit the collection and set the website link.
    When I go to the edit form of the "Some collection" collection
    And I fill in the following:
      | Access URL | http://www.example.com |
    And I press "Publish"
    Then I should see the text "Quick links"
    And I should see the link "Website link"

    When I am not logged in
    And I go to the "Some collection" collection
    Then I should see the text "Quick links"

    # Unset the value, and ensure the link is not visible.
    When I am logged in as a moderator
    And I go to the edit form of the "Some collection" collection
    And I fill in the following:
      | Access URL |  |
    And I press "Publish"
    Then I should not see the text "Quick links"
    And I should not see the link "Website link"

    When I am not logged in
    And I go to the "Some collection" collection
    Then I should not see the text "Quick links"

  Scenario: Test visibility for solutions.
    Given the following solution:
      | title               | Some solution        |
      | abstract            | Some abstract        |
      | description         | Some description     |
      | topic               | Supplier exchange    |
      | solution type       | Digital Solution      |
      | owner               | Organisation example |
      | contact information | Collection editorial |
      | state               | published            |
    When I am not logged in
    And I go to the "Some solution" solution
    Then I should not see the text "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
      | Documentation   |

    Given I am logged in as a moderator
    And I go to the "Some solution" solution
    Then I should not see the text "Quick links"
    And I should not see the following links:
      | Website link    |
      | Release history |
      | Documentation   |

    # Lets not test all links in a separate list of steps to save time.
    Given release:
      | title         | Some release  |
      | is version of | Some solution |
      | state         | published     |
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

    When I am not logged in
    And I go to the "Some solution" solution
    Then I should see the text "Quick links"
    And I should see the following links:
      | Website link    |
      | Release history |
      | Documentation   |
