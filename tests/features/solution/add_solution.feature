@api @group-h
Feature: Test adding solutions.
  In order to manage solutions
  As a moderator
  I need to be able to add "Solution" through UI.

  Scenario: Required fields should be filled in
    Given I am logged in as a facilitator of the "The Portal" collection
    And I go to the homepage of the "The Portal" collection
    And I click "Add solution"

    When I press "Next"
    Then I should see the following error messages:
      | error messages                             |
      | Title field is required.                   |
      | Abstract field is required.                |
      | Description field is required.             |
      | Solution classification field is required. |
      | Domain field is required.                  |
      | Topic field is required.                   |
      | Language availability field is required.   |

    When I fill in the following:
      | Title       | Solution Beta  |
      | Abstract    | Short abstract |
      | Description | Last letter    |
    And I select "Complete Solution" from "Solution classification"
    And I select "Defense" from "Domain"
    And I select "Demography" from "Topic"
    And I select "Bulgarian" from "Language availability"
    And I press "Next"
    And I should see the button "Add new owner"
    And I should see the button "Add existing owner"
    And I press "Add new" at the "Owner" field
    And I press "Next"
    Then I should see the following error messages:
      | error messages                    |
      | Name field is required.           |
      | Type field is required.           |
      | E-mail address field is required. |
    # Create owner.
    And I fill in "owner[form][0][title][0][value]" with "Yancy owner"
    And I check "Company"
    And I fill in "owner[form][0][country][0][target_id]" with "Belgium"
    And I press "Create owner"
    # Create contact information.
    And I fill in "contact[form][0][title][0][value]" with "Yancy Contact"
    And I fill in "contact[form][0][email][value]" with "yancyb@example.com"
    And I press "Create contact information"
    And I press "Next"
    And I select "Logging Service" from "Solution type"
    And I jump to the "Confirmation" of "solution" wizard
    And I press "Propose"
    Then I should see the following error messages:
      | error messages                          |
      | You must accept the Terms of Agreement. |

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

  # Fix this in https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-9780.
  @wip @uploadFiles:logo.png
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
    And I click "Add solution"
    Then I should see the heading "Add solution"
    And the following fields should be present "Title, Abstract, Description, Upload a new file or enter a URL, Logo, Name, E-mail address, Website URL"
    And the following fields should not be present "Groups audience, Other groups, Current workflow state, Langcode, Translation, Motivation"
    # Regression test for ensuring that obsolete content creation value is removed.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3567
    And I should not see the text "Only members can create content"
    And I should see the text "Only facilitators and authors can create content"
    # Regression test to endure that the language terms "Multilingual Code" are not present.
    And the available options in the "Language" select should not include the "Multilingual Code"
    When I fill in the following:
      | Title                 | Espresso is the solution  |
      | Abstract              | Abstract used for testing |
      | Description           | This is a test text       |
      | Geographical coverage | Belgium                   |
      | Name                  | Ernst Brice               |
      | E-mail address        | ernsy1999@gmail.com       |
    And I additionally select "Bulgarian" from "Language"
    And I select "Data Owner" from "Solution type"
    And I select "Demography" from "Topic"
    # Attach a PDF to the documentation.
    And I upload the file "text.pdf" to "Upload a new file or enter a URL"
    # The owner field should have a help text.
    And I should see the text "The Owner is the organisation that owns this entity and is the only responsible for it."
    # Click the button to select an existing owner.
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Organisation example"
    And I press "Add owner"
    # Ensure that the Status field is a dropdown.
    # @see: https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3342
    And I select "Completed" from "Status"
    And the "Show solutions related by EIRA terms" checkbox should not be checked
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
    And I press "Publish"
    # The name of the solution should exist in the block of the relative content in a collection.
    Then I should see the heading "Espresso is the solution"
    # The description is shown in the overview.
    And I should see the text "This is a test text"
    # Most solution fields are not shown in the overview but in the "about" page.
    # @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3224
    And I should not see the link "Belgium"
    And I should not see the link "Bulgarian"
    But I should see the link "Demography"
    And the following email should have been sent:
      | recipient | Wendell Silva                                                                                               |
      | subject   | Interoperable Europe Portal: Your solution has been accepted                                                |
      | body      | Your proposed interoperability solution: "Espresso is the solution" has been validated as per your request. |

    When I am logged in as a facilitator of the "Belgian barista's" collection
    # Make sure that when another solution is added, both are affiliated.
    When I go to the homepage of the "Belgian barista's" collection
    And I click "Add solution"
    When I fill in the following:
      | Title                 | V60 filter coffee solution |
      | Abstract              | Abstract of filter coffee  |
      | Description           | This is a test text        |
      | Geographical coverage | Belgium                    |
      | Name                  | Ajit Tamboli               |
      | E-mail address        | tambotamboli@gocloud.in    |
    And I select "Bulgarian" from "Language"
    Then I select "Data Owner" from "Solution type"
    And I select "E-inclusion" from "Topic"
    # Attach a PDF to the documentation.
    And I upload the file "text.pdf" to "Upload a new file or enter a URL"
    And I attach the file "logo.png" to "Logo"
    # Click the button to select an existing owner.
    And I press "Add existing" at the "Owner" field
    And I fill in "Owner" with "Organisation example"
    And I press "Add owner"
    And I press "Propose"
    Then I should see the heading "V60 filter coffee solution"
    # The name of the solution should exist in the block of the relative content in a collection.
    When I go to the homepage of the "Belgian barista's" collection
    Then I should see the heading "Belgian barista's"
    And I should see the link "Espresso is the solution"
    # The proposed solution should not be visible since it's not yet published.
    But I should not see the link "V60 filter coffee solution"

    When I visit "/user"
    Then I should see the link "V60 filter coffee solution"
    # Clean up the solutions that were created through the UI.
    Then I delete the "V60 filter coffee solution" solution
    Then I delete the "Espresso is the solution" solution

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
    And I press "Next"
    Then I should see the error message "A solution titled Climate change tracker already exists in this collection. Please choose a different title."

    # If a solution with a duplicate name is created in a different collection
    # then this is allowed to be submitted but a warning should be shown to the
    # moderator when approving the proposal.
    Given I am logged in as a member of the "Glacier monitoring" collection
    When I go to the homepage of the "Glacier monitoring" collection
    And I click "Add solution" in the plus button menu
    And I fill in the following:
      | Title                 | Climate change tracker                  |
      | Abstract              | Tracks climate change                   |
      | Description           | Logs retreat of 40 glaciers in Belgium. |
    And I select "Complete Solution" from "Solution classification"
    And I select "Defense" from "Domain"
    And I select "Demography" from "Topic"
    And I select "Logging Service" from "Solution type"
    And I select "Bulgarian" from "Language availability"
    And I press "Next"
    # Create owner.
    And I press "Add new" at the "Owner" field
    And I fill in "owner[form][0][title][0][value]" with "University of Basel"
    And I check "Company"
    And I fill in "owner[form][0][country][0][target_id]" with "Belgium"
    And I press "Create owner"
    # Create contact information.
    And I fill in "contact[form][0][title][0][value]" with "Angela Crespi"
    And I fill in "contact[form][0][email][value]" with "angela_crespi@glacmon.basel-uni.ch"
    And I press "Create contact information"
    And I jump to the "Confirmation" of "solution" wizard
    And I check "I have read and agreed to the Terms of Agreement"
    And I press "Propose"
    Then I should see the heading "Climate change tracker"
    # Check that the warning intended for moderators is not shown to regular
    # users.
    When I click "Edit" in the "Entity actions" region
    Then I should not see the warning message "A solution with the same name exists in a different collection."

    Given I am logged in as a moderator
    And I go to my dashboard
    And I click "Climate change tracker"
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
    And I jump to the "Additional information" of "solution" wizard
    Then I remove the first file from "Logo"
    And I select image #2 as solution logo
    And I jump to the "Confirmation" of "solution" wizard
    And I press "Publish"
    Then the "Cleaning solution" solution logo is image #2
