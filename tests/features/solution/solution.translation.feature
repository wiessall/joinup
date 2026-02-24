@api @group-a
Feature: Translating solutions

  Background:
    Given users:
      | Username    | Roles     |
      | Flora Mod   | moderator |
      | Lee Facil   |           |
      | John Member |           |
      | Ross Auth   |           |
    And collection content:
      | title           | abstract                        | description                    |
      | Test collection | short description of collection | long description of collection |
    And solution content:
      | title         | collection      | abstract                      | description                  |
      | Test solution | Test collection | short description of solution | long description of solution |
    And the following collection user memberships:
      | collection    | user        | roles       |
      | Test solution | Lee Facil   | facilitator |
      | Test solution | John Member |             |

  Scenario Outline: Users who do have edit access can manage translations.
    Given I am logged in as "<user>"

    # Assert language select options/value.
    When I go to the edit form of the "Test solution" solution
    Then the available options in the "Language" select should match the site languages
    And the selected option in the "Language" select should be "en"

    # Create translation.
    When I go to the homepage of the "Test solution" solution
    And I click "Translate" in the "Entity actions" region
    And I click "Add" in the "Polish" row
    Then I should see the heading "Create Polish translation of Test solution"
    When I fill in "Title" with "Testowa solucja"
    And I fill in "Abstract" with "Krótki opis solucji"
    And I fill in "Description" with "Długi opis solucji"
    Then the following fields should not be present "Access URL, Closed, Contact information, Geographical coverage, Logo, Newsletter, Owner, Search keywords, Short ID, Site featured, State, Topic"
    When I press "Confirm"
    And I press "Publish"
    Then I should see the success message "Solution Testowa solucja has been updated."
    And I should see the heading "Testowa solucja"
    And I should not see the following lines of text:
      | "Testowa solucja"     |
      | "Krótki opis solucji" |
      | "Długi opis solucji"  |

    # Edit translation.
    When I click "Translate" in the "Entity actions" region
    Then I should see "Testowa solucja"
    When I click "Edit" in the "Polish" row
    And I fill in "Title" with "Zaktualizowana solucja"
    And I press "Confirm"
    And I press "Publish"
    Then I should see the success message "Solution Zaktualizowana solucja has been updated."
    And I should not see the heading "Testowa solucja"
    And I should see the heading "Zaktualizowana solucja"

    # Delete translation.
    When I click "Translate" in the "Entity actions" region
    And I click "Delete" in the "Polish" row
    Then I should see "Are you sure you want to delete the Polish translation of the content item Zaktualizowana solucja?"
    When I press "Delete Polish translation"
    # @todo Inconsistent messages, see ISAICP-10959
    Then I should see the success message "<delete message>"
    When I click "Translate" in the "Entity actions" region
    Then I should not see "Edit" in the "Polish" row
    And I should see the "Add" in the "Polish" row

    Examples:
      | user      | delete message                                                               |
      | Flora Mod | Polish translation of the Solution Zaktualizowana solucja has been deleted.  |
      | Lee Facil | The content item Zaktualizowana solucja Polish translation has been deleted. |

  Scenario Outline: Users who do not have edit access cannot manage translations.
    Given I am logged in as "<user>"
    When I go to the homepage of the "Test solution" solution
    Then I should not see the link "Edit" in the "Entity actions" region
    And I should not see the link "Translate" in the "Entity actions" region

    Examples:
      | user        |
      | John Member |
      | Ross Auth   |

  @javascript
  Scenario: Create Solution manually in polish
    Given I am logged in as a facilitator of the "Portal support" collection
    When I go to the homepage of the "Portal support" collection
    And I click "Add solution" in the plus button menu
    And I select "Polish" from "Language"
    And I fill in "Title" with "Solucja"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    And I press "Add new owner"
    When I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    And I press "Create contact information"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"

    Given I am logged in as a user with the "developer" role
    When I go to the edit form of the "Solucja" solution
    And I press "Confirm"
    And I press "Publish"
    And I visit "/pl/node/3/solution/solucja/translations"
    Then I should see "Solucja" in the "Polish (Original language)" row
    And I should see "Source" in the "Polish (Original language)" row
