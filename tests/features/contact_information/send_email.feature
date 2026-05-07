@api @group-c @javascript
Feature: Send email to contact information
  Authenticated user should send email to contact information

  Scenario: Send email to contact information
    Given the following "collection" content:
      | title          |
      | Rally fanatics |
    Given the following "contact_information" content:
      | name        | email                   |
      | Some Resend | some.resend@example.com |
    Given the following "solution" content:
      | title         | contact information | collection     |
      | Some solution | Some Resend         | Rally fanatics |
    And the following email confirmation request:
      | email                   | status    |
      | some.resend@example.com | confirmed |

    When I am logged in as a user with the "authenticated" role
    When I go to the "Some solution" solution
    Then I should see the link "Some Resend"
    When I click "Some Resend"
    Then a modal should open
    And I select "Other" from "Type of support"
    And I fill in "Subject" with "test message"
    And I fill in "Message" with "test"
    And I attach the file "logo.png" to "Attachment"
    And I press "Send e-mail" in the "Modal buttons" region
    And the email sent to "some.resend@example.com" with subject "test message" contains the text:
      | /collection/rally-fanatics/solution/some-solution |
      | Type of support: Other                            |
      | Message: test                                     |
    And the email sent to "some.resend@example.com" with subject "test message" contains "logo.png" attachment
