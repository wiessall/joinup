@api @group-f
Feature:
  As an owner of a group
  In order to make the URL of my group easier to read
  I need to be able to provide a short ID to my group.

  Scenario: Short IDs are unique in collections.
    Given the following "collection" content:
      | title              | short ID |
      | Scientific Studies | sst-2020 |
    And the following "owner" content:
      | name                 | owner type |
      | Organisation example | Company    |

    When I am logged in as an "authenticated user"
    And I go to the propose collection form

    # Assert the Short ID description is present.
    Then I should see the following lines of text:
      | Used as the web address (URL). Example: a collection named 'European Commission' could use 'eu-commission'. |
      | Cannot be changed once the collection is published.                                                         |
      | The ID is between 4-26 characters and consists of lowercase letters, numbers and the dash symbol (-).       |

    When I press "Add existing" at the "Owner" field
    And I fill in the following:
      | Title       | Structural Solar Traces    |
      | Abstract    | Dummy text                 |
      | Description | Structural Solar Traces    |
      | Owner       | Organisation example       |
      # Contact information data.
      | Name        | Contact person             |
      | E-mail      | contact_person@example.com |
    And I select "Demography" options from "Topic"
    And I fill in "Short ID" with "sst-2020"
    And I press "Propose"
    Then I should see the inline form error message "Content with Short ID sst-2020 already exists. Please choose a different Short ID."

    # The short ID should be lowercase.
    And I fill in "Short ID" with "SSTR-2020"
    And I press "Propose"
    Then I should see the inline form error message "Short ID must be 4–26 characters: only lowercase letters, digits, and hyphens allowed."

    And I fill in "Short ID" with "sstr-2020"
    And I press "Propose"
    And I should see the heading "Structural Solar Traces"

    When I go to the edit form of the "Structural Solar Traces" collection
    Then the following fields should not be disabled "Short ID"

    # Publish the collection.
    When I am logged in as a moderator
    When I go to the edit form of the "Structural Solar Traces" collection
    And I press "Publish"
    Then I should see the heading "Structural Solar Traces"

    # Check that the "Short ID" field is not disabled for moderators.
    When I click "Edit" in the "Entity actions" region
    Then the following fields should not be disabled "Short ID"

    # Check that the field is not editable any more for facilitators once the
    # collection is published.
    When I am logged in as a facilitator of the "Structural Solar Traces" collection
    When I go to the edit form of the "Structural Solar Traces" collection
    Then the following fields should be disabled "Short ID"

  @javascript
  Scenario: Custom URL Identifiers are unique in solutions.
    Given the following "collection" content:
      | title              | short ID |
      | Scientific Studies | sst-2020 |
    And the following "solution" content:
      | title                         | short ID | collection         |
      | Who knows what a solution is? | wkwasi   | Scientific Studies |

    When I am logged in as a facilitator of the "Scientific Studies" collection
    And I go to the homepage of the "Scientific Studies" collection
    And I click "Add solution" in the plus button menu

    When I fill in "Title" with "I know what a solution is"
    And I fill in "Abstract" with "Some summary"
    And I enter "Dummy text" in the "Description" wysiwyg editor
    And I select "Demography" options from "Topic"
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    And I press "Create contact information"
    And I click "Additional information"
    And I select "Data Owner" options from "Solution type"
    # Assert the Custom URL Identifier description.
    Then I should see the following lines of text:
      | Used as the web address (URL). Example: a solution named 'European Standards for Internet Of Things' could use 'es-iot'. |
      | Cannot be changed once the solution is published.                                                                        |
      | The ID is between 4-26 characters and consists of lowercase letters, numbers and the dash symbol (-).                    |

    # Custom URL Identifier is case insensitive.
    And I fill in "Custom URL Identifier" with "wkwasi"
    And I press "Confirm"
    Then I should see the inline form error message "Content with Custom URL Identifier wkwasi already exists. Please choose a different Custom URL Identifier."

    # The short ID should be lowercase.
    And I fill in "Custom URL Identifier" with "SST-2020"
    And I press "Confirm"
    Then I should see the inline form error message "Custom URL Identifier must be 4–26 characters: only lowercase letters, digits, and hyphens allowed."

    # The short ID should not contain non-ASCII chars.
    And I fill in "Custom URL Identifier" with "ñ字Ωش★₿©"
    And I press "Confirm"
    Then I should see the inline form error message "Custom URL Identifier must be 4–26 characters: only lowercase letters, digits, and hyphens allowed."

    # The short ID is only unique among solutions, it is OK to reuse an existing
    # short ID from a collection.
    When I fill in "Custom URL Identifier" with "sst-2020"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "I know what a solution is"

    When I go to the edit form of the "I know what a solution is" solution
    Then the following fields should not be disabled "Custom URL Identifier"

    # Publish the solution.
    When I am logged in as a moderator
    And I go to the edit form of the "I know what a solution is" solution
    And I press "Confirm"
    And I press "Publish"
    Then I should see the heading "I know what a solution is"

    # Check that the "Custom URL Identifier" field is not disabled for moderators.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then the following fields should not be disabled "Custom URL Identifier"

    # Check that the field is not editable for facilitators.
    When I am logged in as a facilitator of the "I know what a solution is" solution
    And I go to the edit form of the "I know what a solution is" solution
    Then the following fields should be disabled "Custom URL Identifier"

    Then I delete the "I know what a solution is" solution
