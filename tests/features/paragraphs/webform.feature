@api @group_e
Feature:
  As a site builder of the site
  I need to be able to use "Webform" paragraph for content.

  Background:
    Given the following collection:
      | title | Webform collection |
      | state | published          |

  @javascript @uploadFiles:logo.png
  Scenario: Add webform paragraph.
    Given custom_page content:
      | title           | collection         | state     |
      | Webform example | Webform collection | published |
    And I append "layout" to "field_paragraphs_body" field in "node" entity with following "Webform example" title:
      | behavior_settings | a:3:{s:17:"layout_paragraphs";a:4:{s:6:"layout";s:13:"layout_onecol";s:6:"config";a:1:{s:5:"label";s:0:"";}s:11:"parent_uuid";N;s:6:"region";N;}s:16:"background_theme";a:0:{}s:11:"extra_class";a:0:{}} |

    Then I am logged in as a moderator
    And I go to the edit form of the "Webform example" "custom page"
    And I click "Choose component"
    And I click "Webform"
    And I select "Public Sector Tech Watch" from "Webform" in the "Modal content" region
    And I press "Save" in the "Modal buttons" region
    And I press "Update"
    When I should see the heading "Submitter information"
    And I should see the heading "Case Information"

    Then I log out
    And I go to the "Webform example" custom page
    When I should not see the heading "Submitter information"
    And I should see the text "Please login to access the form."

    Then I am logged in as a user with the "authenticated" role
    And I go to the "Webform example" custom page

    # Check validation errors.
    Then I press "Submit"
    When I should see the following error messages:
      | error messages                                                                                         |
      | Case title field is required.                                                                          |
      | Technology field is required.                                                                          |
      | Public sector organisation field is required.                                                          |
      | Public Sector Organisation responsible for the case field is required.                                 |
      | Geographical coverage field is required.                                                               |
      | Source (URL) field is required.                                                                        |
      | Description field is required.                                                                         |
      | Is the technological solution available as Open Source? field is required.                             |
      | Status field is required.                                                                              |
      | Which area does the solution impact the most? field is required.                                       |
      | Did the solution or will the solution increase the openness of government? field is required.          |
      | I have read and agreed to the Joinup Legal Notice and the Joinup Privacy Statement. field is required. |

    # Fill form.
    Then I should see the heading "Submitter information"
    And I fill in "First name" with "John"
    And I fill in "Last name" with "Smith"
    And I fill in "Contact email" with "john.smith@example.com"
    And I fill in "Submitter organisation" with "Smith's organisation"

    Then I should see the heading "Case Information"
    And I fill in "Case title" with "Smith's case"
    And I select "Artificial Intelligence" from "Technology"
    And I fill in "Public sector organisation" with "Smith's sector"
    And I check "Central Government"
    And I check "Regional Government"
    And I select "Denmark" from "Geographical coverage"
    And I select "Local" from "Geographical extent"
    And I select "Health" from "Functions of Government"
    And I fill in "Source (URL)" with "http://example.com"
    And I fill in "Description" with "Some description"
    And I select the radio button "Yes"
    And I select "Not in use anymore" from "Status"
    And I select the radio button "Public service quality"
    And I check the box "Services are more personalised"
    And I check the box "Yes, it increased / will increase transparency of public sector operations"
    And I select the radio button "No, we developed the solution internally"
    And I attach the file "logo.png" to "Additional info"

    # Check webform patch.
    And I press "Add organisation"

    Then I should see the heading "Terms and Conditions"
    And I check the box "I have read and agreed to the Joinup Legal Notice and the Joinup Privacy Statement."

    Then I should see the heading "Send yourself a copy"
    And I check the box "Send me an email with a copy of this form"

    # Submit form.
    Then I press "Submit"
    When I should not see the success message "Status messageYour use case has been submitted successfully. Soon you will be informed by the Public Sector Tech Watch team about the next steps."
