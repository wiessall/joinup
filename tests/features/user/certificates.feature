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
    And I attach the file "text.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the profile of "Sara the User"
    And I should see the link "1 Certificate"
    Then I should see the following tiles in the correct order:
      | Archived IOPEU Solution |
      | Portal support          |
    Then the logo logo.png should be shown in the "Archived IOPEU Solution" certificate tile
    And  I see the text "Issuing date: 06/12/2026" in the "Archived IOPEU Solution" tile
    When I go to the edit form of the "Sara the User" user
    Then I should see the link "Archived IOPEU Solution"
    And I should see the text "Certificate of course"
    Then the "IEF table" table should contain the following columns:
      | Course                  | Status    |
      | Archived IOPEU Solution | Published |
    When I press "Remove" in the "Archived IOPEU Solution" row
    And I press "Remove" in the "Are you sure you want to remove Certificate of course" row
    Then I should not see the text "Certificate of course"
    When I press "Save"
    And I go to the edit form of the "Sara the User" user
    Then I should not see the link "Archived IOPEU Solution"

    Examples:
      | actor               |
      | Peter the Moderator |
      | Sara the User       |

  @javascript
  Scenario: When user is blocked/unblocked certificates should be unpublished/published
    When I am logged in as a user with the moderator role
    And I go to the edit form of the "Sara the User" user
    Then I should see the text "Certificates"
    When I press the "Add new certificate" button
    Then field should be present "Issuing date"
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "text.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    # Block the user
    Then I am on the homepage
    When I click "People"
    Then I check "Sara the User"
    And I select "Block the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Block the selected user(s) was applied to 1 item."
    And I go to the edit form of the "Sara the User" user
    Then I should see the link "Archived IOPEU Solution"
    And I should see the text "Certificate of course"
    Then the "IEF table" table should contain the following columns:
      | Course                  | Status      |
      | Archived IOPEU Solution | Unpublished |
    # Unblock the user
    Then I am on the homepage
    When I click "People"
    Then I check "Sara the User"
    And I select "Unblock the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Unblock the selected user(s) was applied to 1 item."
    And I go to the edit form of the "Sara the User" user
    Then I should see the link "Archived IOPEU Solution"
    And I should see the text "Certificate of course"
    Then the "IEF table" table should contain the following columns:
      | Course                  | Status    |
      | Archived IOPEU Solution | Published |

  @javascript
  Scenario: Users have no access to certificate canonical, edit and delete page
    When I am logged in as "Peter the Moderator"
    And I go to the edit form of the "Sara the User" user
    Then I should see the text "Certificates"
    When I press the "Add new certificate" button
    Then field should be present "Issuing date"
    And I type "Archived IOPEU" in the Course autocomplete field
    And I pick "Archived IOPEU Solution" from the Course autocomplete suggestions
    And I attach the file "text.pdf" to "files[certificates_form_0_certificate_0]"
    And I press "Create certificate"
    And I press "Save"
    Then I go to the "Certificate of course Archived IOPEU Solution" certificate
    Then I should see the heading "Page not found"
    Then I go to the edit form of the "Certificate of course Archived IOPEU Solution" certificate
    Then I should see the heading "Page not found"
    Then I go to the delete form of the "Certificate of course Archived IOPEU Solution" certificate
    Then I should see the heading "Page not found"
