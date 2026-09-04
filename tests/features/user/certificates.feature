@api @group-i
Feature: User certificates
  To show my accomplishments and achievements,
  As a Portal user, I would like to upload the certificates that I have earned
  on my profile on the Portal

  Background:
    Given the following solution content:
      | title                    | collection                   | state     | logo     |
      | Published IOPEU Solution | Interoperable Europe Academy | published |          |
      | Archived IOPEU Solution  | Interoperable Europe Academy | archived  | logo.png |
      | Draft IOPEU Solution     | Interoperable Europe Academy | draft     |          |
      | Portal IOPEU Solution    | Portal support               | published |          |
    And the following news content:
      | title                     | collection                   | body         |
      | News about IOPEU Solution | Interoperable Europe Academy | Just a test! |

    And the following users:
      | Username            | Roles     | First name | Family name   |
      | Peter the Moderator | moderator | Peter      | the Moderator |
      | Sara the User       |           | Sara       | the User      |

  @javascript
  Scenario: Unrelated and unpublished solutions don't appear as course
    When I am logged in as "Peter the Moderator"
    And I visit "user/edit"
    And I press the "Add new certificate" button
    And I type "IOPEU Solution" in the Course autocomplete field
    Then I should see "Published IOPEU Solution"
    And I should see "Archived IOPEU Solution"
    But I should not see "Draft IOPEU Solution"
    And I should not see "Portal IOPEU Solution"
    And I should not see "News about IOPEU Solution"

  @javascript
  Scenario Outline: Users and moderators can add, edit and delete certificates
    When I am logged in as "<actor>"
    And I go to the edit form of the "Sara the User" user
    Then I should see the text "Certificates"
    When I press the "Add new certificate" button
    Then field should be present "Issuing date"
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I fill in "certificates[form][0][issuing_date][0][value][date]" with "12/06/2026"
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then break
    Then I go to the profile of "Sara the User"
    And I should see the link "1 Certificate"
    Then I should see the following tiles in the correct order:
      | Archived IOPEU Solution |
      | Portal support          |
    Then the logo should be shown in the "Archived IOPEU Solution" certificate tile
    And  I see the text "Issuing date: 06/12/2026" in the "Archived IOPEU Solution" tile
    When I go to the edit form of the "Sara the User" user
    Then I should see the link "Archived IOPEU Solution"
    When I press "Remove" in the "Archived IOPEU Solution" row
    And I press "Remove" in the "Are you sure you want to remove Certificate of course" row
    Then I should not see the text "Certificate of course"
    And I press "Save"
    When I am logged in as a user with the "administrator" role
    And I go to the edit form of the "Sara the User" user
    Then I should not see the link "Archived IOPEU Solution"

    Examples:
      | actor               |
      | Peter the Moderator |
      | Sara the User       |

  @javascript
  Scenario: When user is blocked/unblocked certificates should be unpublished/published
    When I am logged in as "Peter the Moderator"
    And I go to the edit form of the "Sara the User" user
    And user "Sara the User" should not have the "certificate" badge
    Then I should see the text "Certificates"
    When I press the "Add new certificate" button
    Then field should be present "Issuing date"
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    And user "Sara the User" should have the "certificate" badge
    # Block the user
    When I go to the edit form of the "Sara the User" user
    And I select the radio button "Blocked"
    And I press "Save"
    When I go to the public profile of "Sara the User"
    Then the page should show only the tiles "Portal support"
    And user "Sara the User" should not have the "certificate" badge
    # Unblock the user
    When I go to the edit form of the "Sara the User" user
    And I select the radio button "Active"
    And I press "Save"
    When I go to the public profile of "Sara the User"
    Then the page should show the tiles "Archived IOPEU Solution, Portal support"
    And user "Sara the User" should have the "certificate" badge

  @javascript
  Scenario: Certificate tiles on the user profile page do not show contextual links
    When I am logged in as "Peter the Moderator"
    And I go to the edit form of the "Sara the User" user
    And I press the "Add new certificate" button
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the public profile of "Sara the User"
    And I should not see the contextual links "Edit, Delete" in the "Archived IOPEU Solution" tile

  @javascript
  Scenario: Certificate title is safely truncated when the course title exceeds the title field limit
    Given the following solution content:
      | title                                                                                                                                                                                                                                                      | collection                   | state     |
      | Long Running Solution Title That Will Trigger Truncation In The Certificate Entity Bundle Class Because It Exceeds The Two Hundred And Thirty Three Character Limit For Course Titles In This Context And Therefore Absolutely Needs To Be Shortened Here! | Interoperable Europe Academy | published |
    When I am logged in as "Peter the Moderator"
    And I go to the edit form of the "Sara the User" user
    And I press the "Add new certificate" button
    And I type "Long Running Solution Title" in the Course autocomplete field
    And I pick "Long Running Solution Title That Will Trigger Truncation In The Certificate Entity Bundle Class Because It Exceeds The Two Hundred And Thirty Three Character Limit For Course Titles In This Context And Therefore Absolutely Needs To Be Shortened Here!" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the profile of "Sara the User"
    And I should see the link "1 Certificate"

  @javascript
  Scenario: Users have no access to certificate canonical, edit and delete page
    When I am logged in as "Peter the Moderator"
    And I go to the edit form of the "Sara the User" user
    Then I should see the text "Certificates"
    When I press the "Add new certificate" button
    Then field should be present "Issuing date"
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the "Certificate of course Archived IOPEU Solution" certificate
    And I should see the following warning messages:
      | Warning messages                                                                                               |
      | This page is configured to apply "Page not found" Rabbit Hole action, but you have permission to see the page. |
    Then I go to the edit form of the "Certificate of course Archived IOPEU Solution" certificate
    Then I should see the heading "Page not found"
    Then I go to the delete form of the "Certificate of course Archived IOPEU Solution" certificate
    Then I should see the heading "Page not found"

  @javascript
  Scenario: Certificate file is deleted when certificate is deleted
    Given I am logged in as a user with the "developer" role
    And I go to the edit form of the "Sara the User" user
    And I press the "Add new certificate" button
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    And I go to "/admin/content/files"
    Then I should see the link "certificate_1.pdf"
    And I go to the edit form of the "Sara the User" user
    When I press "Remove" in the "Archived IOPEU Solution" row
    And I press "Remove" in the "Are you sure you want to remove Certificate of course" row
    And I press "Save"
    And I go to "/admin/content/files"
    Then I should not see the link "certificate_1.pdf"

  @javascript
  Scenario: Certificate and badge is deleted when course is deleted
    Given I am logged in as a user with the "developer" role
    And I go to the edit form of the "Sara the User" user
    And I press the "Add new certificate" button
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "certificate_1.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the profile of "Sara the User"
    And I should see the link "1 Certificate"
    And user "Sara the User" should have the "certificate" badge
    Then I go to the edit form of the "Archived IOPEU Solution" solution
    And I click "Delete"
    And I press "Delete" in the "Modal buttons" region
    Then I go to the profile of "Sara the User"
    And I should not see the link "1 Certificate"
    And user "Sara the User" should not have the "certificate" badge
