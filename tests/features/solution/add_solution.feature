@api @group-h
Feature: Test adding solutions.
  In order to manage solutions
  As a moderator
  I need to be able to add "Solution" through UI.

  @javascript @generateMedia @generateMediaTypes:solution_logo
  Scenario: Required fields should be filled in
    Given I am logged in as a facilitator of the "The Portal" collection
    And I go to the homepage of the "The Portal" collection
    And I click "Add solution" in the plus button menu

    And I press "Confirm"
    Then I should see the following inline form error messages:
      | error messages                             |
      | Title field is required.                   |
      | Abstract field is required.                |
      | Description field is required.             |
      | Solution classification field is required. |
      | Domain field is required.                  |
      | Topic field is required.                   |
      | Language availability field is required.   |

    # Test that the tab doesn't change when an image is uploaded.
    When I click "Additional information"
    And I select image #2 as solution logo
    Then I should see the text "Logo"

    When I click "Solution information"
    # Check Domain field description.
    And I should see the text "Available info for Government functions"
    And I should see the link "Government functions"
    When I fill in "Title" with "Solution Beta"
    And I fill in "Abstract" with "Short abstract"
    And I enter "Last letter" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    And I press "Add new owner"
    And I press "Confirm"
    Then I should see the following inline form error messages:
      | error messages                    |
      | Name field is required.           |
      | Type field is required.           |
      | Country field is required.        |
      | E-mail address field is required. |
    # Create owner. Validation errors must have activated the tab "Contact details".
    When I fill in "Name" with "Organisation valid"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I fill in "Name" with "Contact Rapazoglou"
    And I fill in "E-mail address" with "contact_Rapazoglou@example.com"
    Then I press "Create contact information"
    And I press "Confirm"
    And I press "Propose"
    Then I should see the inline form error message "You must accept the Terms of Agreement."

  Scenario: "Add solution" button should only be shown to moderators and facilitators.
    Given collection content:
      | title                    |
      | Collection solution test |

    When I am logged in as a moderator
    And I go to the homepage of the "Collection solution test" collection
    Then I should see the link "Add solution"

    When I am logged in as a "facilitator" of the "Collection solution test" collection
    And I go to the homepage of the "Collection solution test" collection
    Then I should see the link "Add solution"

    When I am logged in as an "authenticated user"
    And I go to the homepage of the "Collection solution test" collection
    Then I should not see the link "Add solution"
    # Regression test to ensure that the user has not access to the 'Propose solution' page.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-2842
    And I should not see the link "Propose solution"

    When I am an anonymous user
    And I go to the homepage of the "Collection solution test" collection
    Then I should not see the link "Add solution"

  @javascript
  Scenario: Add solution as a collection facilitator.
    Given collection content:
      | title             | logo     |
      | Belgian barista's | logo.png |
    And owner content:
      | name                 | owner type                   |
      | Organisation example | Company, Industry consortium |
    Given users:
      | Username      | Roles     | E-mail                 | First name | Family name |
      | Ruth Lee      | moderator | Ruth.Lee@test.com      | Ruth       | Lee         |
      | Wendell Silva |           | Wendell.Silva@test.com | Wendell    | Silva       |
    And collection user memberships:
      | collection        | user          | roles              |
      | Belgian barista's | Wendell Silva | owner, facilitator |

    When I am logged in as "Wendell Silva"
    And I go to the homepage of the "Belgian barista's" collection
    And I click "Add solution" in the plus button menu
    Then I should see the heading "Add solution"
    And the following fields should be present "Title, Abstract, Description, Related documents, Logo, Name, E-mail address, Website URL"
    And the following fields should not be present "Groups audience, Other groups, Current workflow state, Langcode, Translation, Motivation"
    # Regression test for ensuring that obsolete content creation value is removed.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3567
    And I should not see the text "Only members can create content"
    # Regression test to endure that the language terms "Multilingual Code" are not present.
    And the "Language availability" tagify should not contain the following option "Multilingual Code"
    And I fill in "Title" with "Espresso is the solution"
    And I fill in "Abstract" with "Abstract used for testing"
    And I enter "This is a test text" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # The owner field should have a help text.
    And I should see the text "The Owner is the organisation that owns this entity and is the only responsible for it."
    # Click the button to select an existing owner.
    And I press "Add existing owner"
    And I fill in "Owner" with "Organisation example"
    And I press "Add owner"
    # Create contact information.
    And I press "Create contact information"
    And I fill in "contact[form][0][title][0][value]" with "Ernst Brice"
    And I fill in "contact[form][0][email][value]" with "ernsy1999@gmail.com"
    And I press "Create contact information"
    And I click "Additional information"
    And I select "Data Owner" options from "Solution type"
    # Attach a PDF to the documentation.
    And I press "Upload file"
    And I attach the file "text.pdf" to "files[resources_0_subform_resource_file_0]"
    # Ensure that the Status field is a dropdown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3342
    And I select "Completed" from "Status"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then the email sent to "Ruth Lee" with subject "Interoperable Europe Portal: A new solution has been proposed" contains the following lines of text:
      | Wendell Silva has proposed a new Interoperability solution: "Espresso is the solution" on Interoperable Europe Portal. |
      | If you think this action is not clear or not due, please contact Portal Support at                                     |
    And I should see "Thank you for proposing a solution. Your request is currently pending approval by the site administrator."
    And Log entry with the message "A new solution entity titled Espresso is the solution has been created by Wendell Silva in Belgian barista's." should exist.

    # Regression test for the non required field, 'Logo'.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3328
    And I should not see the following error messages:
      | error messages          |
      | Logo field is required. |
    But I should see a logo on the header
    And I should see the heading "Espresso is the solution"
    When I am logged in as "Ruth Lee"
    And I go to the edit form of the "Espresso is the solution" solution
    And I press "Confirm"
    And I press "Publish"
    # The name of the solution should exist in the block of the relative content in a collection.
    Then I should see the heading "Espresso is the solution"
    # The description is shown in the overview.
    And I should see the text "This is a test text"
    # Most solution fields are not shown in the overview but in the "about" page.
    # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3224
    And I should not see the link "Belgium" in the "Content" region
    And I should not see the link "Bulgarian" in the "Content" region
    But I should see the link "Demography"
    And the following email should have been sent:
      | recipient | Wendell Silva                                                                                               |
      | subject   | Interoperable Europe Portal: Your solution has been accepted                                                |
      | body      | Your proposed interoperability solution: "Espresso is the solution" has been validated as per your request. |

    When I am logged in as "Wendell Silva"
    # Make sure that when another solution is added, both are affiliated.
    When I go to the homepage of the "Belgian barista's" collection
    And I click "Add solution" in the plus button menu
    And I fill in "Title" with "V60 filter coffee solution"
    And I fill in "Abstract" with "Abstract of filter coffee"
    And I enter "This is a test text" in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "E-inclusion" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Click the button to select an existing owner.
    And I press "Add existing owner"
    And I fill in "Owner" with "Organisation example"
    And I press "Add owner"
    # Create contact information.
    And I press "Create contact information"
    And I fill in "contact[form][0][title][0][value]" with "Ajit Tamboli"
    And I fill in "contact[form][0][email][value]" with "tambotamboli@gocloud.in"
    And I press "Create contact information"
    And I click "Additional information"
    Then I select "Data Owner" options from "Solution type"
    # Attach a PDF to the documentation.
    And I press "Upload file"
    And I attach the file "text.pdf" to "files[resources_0_subform_resource_file_0]"
    And I press "Add remote file URL"
    And I fill in "resources[1][subform][remote_file_url][0][uri]" with "http://example.com/guzzle"
    And I attach the file "logo.png" to "Logo"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "V60 filter coffee solution"
    When I go to the edit form of the "V60 filter coffee solution" solution
    And I click "Additional information"
    Then I press "resources_0_edit"
    And I fill in "resources[0][subform][resource_file][0][description]" with "My uploaded file"
    Then I press "resources_1_edit"
    And I fill in "Link text" with "My remote file"
    And I press "Confirm"
    And I press "Propose"
    And I should see "Documentation"
    And I should not see "text.pdf"
    And I should not see "logo.png"
    And I should see "My uploaded file"
    And I should see "My remote file"

    # The name of the solution should exist in the block of the relative content in a collection.
    When I go to the homepage of the "Belgian barista's" collection
    Then I should see the heading "Belgian barista's"
    And I should see the link "Espresso is the solution"
    # The proposed solution should not be visible since it's not yet published.
    But I should not see the link "V60 filter coffee solution"

    When I visit "/user"
    Then I should see the link "V60 filter coffee solution"

  @javascript
  Scenario: Create a solution with a name that already exists
    Given collection content:
      | title              |
      | Ocean studies      |
      | Glacier monitoring |
    And solution content:
      | title                  | abstract              | description                                       | collection    |
      | Climate change tracker | Tracks climate change | Atlantic salmon arrived after the Little Ice Age. | Ocean studies |
    And owner content:
      | name                | owner type          |
      | University of Basel | Industry consortium |

    # No two solutions with the same name may be created in the same collection.
    Given I am logged in as a member of the "Ocean studies" collection
    When I go to the homepage of the "Ocean studies" collection
    And I click "Add solution" in the plus button menu
    And I fill in "Title" with "Climate change tracker"
    And I press "Confirm"
    Then I should see the inline form error message "A solution titled Climate change tracker already exists in this collection. Please choose a different title."

    # If a solution with a duplicate name is created in a different collection
    # then this is allowed to be submitted but a warning should be shown to the
    # moderator when approving the proposal.
    Given I am logged in as a member of the "Glacier monitoring" collection
    When I go to the homepage of the "Glacier monitoring" collection
    And I click "Add solution" in the plus button menu
    And I fill in "Title" with "Climate change tracker"
    And I fill in "Abstract" with "Tracks climate change"
    And I enter "Logs retreat of 40 glaciers in Belgium." in the "Description" wysiwyg editor
    And I select "Complete Solution" options from "Solution classification"
    And I select "Defense" options from "Domain"
    And I select "Demography" options from "Topic"
    And I select "Bulgarian" options from "Language availability"
    And I click "Contact details"
    # Create owner.
    And I press "Add new owner"
    And I fill in "Name" with "University of Basel"
    And I select "Company" options from "Type"
    And I select "Belgium" from "Country"
    And I press "Create owner"
    # Create contact information.
    And I press "Create contact information"
    And I fill in "contact[form][0][title][0][value]" with "Angela Crespi"
    And I fill in "contact[form][0][email][value]" with "angela_crespi@glacmon.basel-uni.ch"
    And I click "Additional information"
    And I select "Logging Service" options from "Solution type"
    And I press "Confirm"
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "Climate change tracker"
    # Check that the warning intended for moderators is not shown to regular
    # users.
    When I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I should not see the warning message "A solution with the same name exists in a different collection."

    Given I am logged in as a moderator
    And I go to my dashboard
    And I click "Climate change tracker"
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I should see the warning message "A solution with the same name exists in a different collection."

  @javascript @generateMedia @generateMediaTypes:solution_logo
  Scenario: Tests the image library widget.
    Given collection content:
      | title         |
      | Ocean studies |
    And solution content:
      | title             | collection    |
      | Cleaning solution | Ocean studies |
    And I am logged in as a moderator

    When I go to the edit form of the "Cleaning solution" solution
    And I click "Additional information"
    Then I remove the first file from "Logo"
    And I select image #2 as solution logo
    And I press "Confirm"
    And I press "Publish"
    Then the "Cleaning solution" solution logo is image #2

  @javascript
  Scenario: User should see all topic categories.
    Given I am logged in as a facilitator of the "The Portal" collection
    And I go to the homepage of the "The Portal" collection
    And I click "Add solution" in the plus button menu
    When the "Topic" tagify autocomplete should contain the following option groups:
      | Economy and Welfare  |
      | eGov                 |
      | E-health Dpt.        |
      | HR Dpt.              |
      | Info                 |
      | Law and Justice      |
      | Social and Political |

  @javascript
  Scenario: Previous and Next buttons should be present and functional.
    Given I am logged in as a facilitator of the "The Portal" collection
    And I go to the homepage of the "The Portal" collection
    And I open the plus button menu
    And I click "Add solution"

    Then I should see the button "Previous"
    And I should see the button "Next"

    # Initially there is no fragment.
    And the url should match "^/collection/.*/solution/add$"
    # Navigating with the links should add the fragment accordingly.
    When I press "Next"
    Then the url should match "^/collection/.*/solution/add#edit-group-contact-details$"
    When I press "Next"
    Then the url should match "^/collection/.*/solution/add#edit-group-additional-information$"
    When I press "Previous"
    Then the url should match "^/collection/.*/solution/add#edit-group-contact-details$"
    When I press "Previous"
    Then the url should match "^/collection/.*/solution/add#edit-group-solution-information$"

    # Clicking on a specific tab should update the fragment and affect the
    # "Previous" and "Next" buttons.
    When I click "Contact details"
    Then the url should match "^/collection/.*/solution/add#edit-group-contact-details$"
    When I press "Previous"
    Then the url should match "^/collection/.*/solution/add#edit-group-solution-information$"
    When I click "Contact details"
    Then the url should match "^/collection/.*/solution/add#edit-group-contact-details$"
    When I press "Next"
    Then the url should match "^/collection/.*/solution/add#edit-group-additional-information$"

  @javascript
  Scenario: Custom constraints point to the correct field.
    Given solution content:
      | title | short ID | state     | collection |
      | Test  | qwerty   | published | The Portal |
    Given I am logged in as a moderator
    And I go to the "The Portal" collection
    And I open the plus button menu
    And I click "Add solution"
    When I click "Additional information"
    And I fill in "Custom URL Identifier" with "qwerty"
    And I press "Confirm"
    Then I should see the text "errors have been found:"
    And I should see the link "Custom URL Identifier"
    But I should not see the text "qwerty"
    And I should not see the text "Content with Custom URL Identifier qwerty already exists."
    # The link should point to the "Custom URL Identifier" field.
    When I click "Custom URL Identifier"
    Then I should see the text "Custom URL Identifier"
    And I should see the text "Content with Custom URL Identifier qwerty already exists."
