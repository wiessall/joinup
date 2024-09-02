@api @group-d
Feature: User interface for the File URL field
  In order to be able to upload files or refer to external files
  As an authenticated user
  I need to be able to interact with a field widget that offers both options

  Background:
    Given collection content:
      | title            | state     |
      | Regression tests | published |

  @javascript
  Scenario: Interact with both the 'File upload' and 'External URL' options
    Given I am logged in as a member of the "Regression tests" collection
    # An example of the File URL field is the "Documentation" field in the
    # solution form.
    And I go to the add solution form of the "Regression tests" collection

    # Initially, only the radio button selector should be visible, but no option
    # is selected.
    Then the "Upload file" radio button should not be selected
    And the "Remote file URL" radio button should not be selected
    But the following fields should not be visible "Choose a file,Remote URL"
    And I should not see the text "Allowed types: txt doc docx pdf."
    And I should not see the description "This must be an external URL such as http://example.com." for "Upload a new file or enter a URL"
    And I expand the "Additional fields" accordion section
    # Try to upload a file.
    Given I select the radio button "Upload file"
    Then the following field should be visible "Choose a file"
    And I should see the text "Allowed types: txt doc docx pdf."
    But the following field should not be visible "Remote URL"
    And I should not see the description "This must be an external URL such as http://example.com." for "Upload a new file or enter a URL"

    # Toggle the option. Now the other field and help text should be visible.
    Given I select the radio button "Remote file URL"
    Then the following field should be visible "Remote URL"
    And I should see the button "Add URL"
    And I should see the description "This must be an external URL such as http://example.com." for "Upload a new file or enter a URL"
    But the following field should not be visible "Choose a file"
    And I should not see the text "Allowed types: txt doc docx pdf."

  Scenario: User should be able to remove entity even document's entities do not exist
    Given solution content:
      | title                      | documentation | state     | collection       |
      | Solution without documents | text.pdf      | published | Regression tests |
    And I delete documents from "Solution without documents" solution

    Given I am logged in as a moderator
    When I go to the edit form of the "Solution without documents" solution
    Then the response status code should be 200
    When I click "Delete"
    And I press "Delete"
    Then the response status code should be 200

  @javascript @uploadFiles:logo.png
  Scenario: File URL does not trigger type error after trying to submit an invalid url
    Given document content:
      | title          | abstract | field_paragraphs_body | state | document type | collection       |
      | Document title | Abstract | Body                  | draft | document      | Regression tests |

    Given I am logged in as a moderator
    And I go to the "Document title" document
    And I open the header local tasks menu
    And I click "Edit" in the "Entity actions" region
    Then I should see the text "Upload a new file or enter a URL"
    And I select the radio button "Remote file URL"
    And I fill in "field_file[0][file_url_remote]" with "foo"
    And I press "Add URL"
    Then I should see the error message "The URL foo is not valid"
    And I select the radio button "Upload file"
    And I attach the file "logo.png" to "files[field_file_0][]"
    And I should not see the text "Oops, something went wrong. Check your browser's developer console for more details."
    And I press "Publish"
    Then I should see the heading "Document title"
    And I should see the heading Attachment
    And I should see the link logo.png
