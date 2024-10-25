@api @group-c
Feature: Submit the contact form
  In order to gather feedback from the users
  As a moderator
  I want to collect wishes and grievances through a contact form

  @uploadFiles:logo.png
  Scenario: Submit a message as an anonymous user
    Given users:
      | Username                | Roles     |
      | Valentína Řezník        | moderator |
      | Oluwakanyinsola Opeyemi | moderator |

    # There should be a link to the contact form in the footer.
    Given I am not logged in
    When I visit "/?destination=collections"
    And I click "Contact Portal Support" in the "Footer" region
    And I should see the heading "Contact"
    And the "Category" select should contain the following options:
      | - Select a value -             |
      | Technical problem / bug report |
      | Question on usage              |
      | Feature request                |
      | Legal issue                    |
      | Inappropriate content          |
      | EU Login question              |
      | Interoperable Europe question  |
      | Other                          |
    And I should see the text "Submissions of this form are processed by a contractor of the European Commission."
    And I fill in the following:
      | First name     | Wolverine                   |
      | Last name      | Wolves                      |
      | Organisation   | The Deaf-Mute Wolves        |
      | E-mail address | oswine@example.pt           |
      | Category       | other                       |
      | Subject        | Screen reader accessibility |
      | Message        | Dear sir, madam, ...        |
    And I attach the file "logo.png" to "Attachment"
    And I press "Submit"
    Then I should be on "collections"

    And I am on the homepage
    When I click "Contact Portal Support" in the "Footer" region
    And I fill in the following:
      | First name     | Oswine                      |
      | Last name      | Wulfric                     |
      | Organisation   | The Deaf-Mute Society       |
      | E-mail address | oswine@example.za           |
      | Category       | other                       |
      | Subject        | Screen reader accessibility |
      | Message        | Dear sir, madam, ...        |
    And I attach the file "logo.png" to "Attachment"
    And I press "Submit"

    # Both moderators should have received the notification e-mail.
    Then the following email should have been sent:
      | template           | Contact form submission                       |
      | from               | digit-joinup@ec.europa.eu                     |
      | recipient_mail     | DIGIT-JOINUP-SUPPORT-EXT-FORWARD@ec.europa.eu |
      | subject            | Portal - Contact form submission              |
      | body               | Dear sir, madam, ...                          |
      | signature_required | no                                            |
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |
    And I should be on the homepage
    When I click the link for the "logo.png" attachment in the contact form email sent to "DIGIT-JOINUP-SUPPORT-EXT-FORWARD@ec.europa.eu"
    # For anonymous users, the file should not be accessible.
    # The redirection to the EU login page returns a 200 code instead of a 403
    # so check for the page header instead.
    Then I should see the heading "Sign in to continue"
    When I am logged in as a moderator
    # Private files from contact forms are stored in
    # "<base url>/<private system path>/contact_form/<year>_<month>/<file>"
    And I click the link for the "logo.png" attachment in the contact form email sent to "DIGIT-JOINUP-SUPPORT-EXT-FORWARD@ec.europa.eu"
    # The server responds with an image.
    Then the content type of the response should be 'image/png'

  Scenario: Check required fields
    When I am on the contact form
    And I press "Submit"
    Then I should see the following error messages:
      | error messages                    |
      | E-mail address field is required. |
      | First name field is required.     |
      | Last name field is required.      |
      | Category field is required.       |
      | Message field is required.        |
      | Subject field is required.        |

  Scenario: Credentials of an authenticated user are prefilled
    Given user:
      | Username    | Wally Papamichael           |
      | First name  | Wally                       |
      | Family name | Papamichael                 |
      | E-mail      | w.papamichael@schiffmann.de |
    Given I am logged in as "Wally Papamichael"
    When I am on the contact form
    Then the "First name" field should contain "Wally"
    And the "Last name" field should contain "Papamichael"
    And the "E-mail address" field should contain "w.papamichael@schiffmann.de"

  @javascript
  Scenario: Regression test for ISAICP-7104
  See https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-7104

    Given I am not logged in
    Then I go to the contact form
    And I fill in "First name" with "Diana"
    And I fill in "Last name" with "Chaves"
    And I fill in "Organisation" with "Lucotores State"
    And I fill in "E-mail address" with "doana.chaves@free.pe"
    And I select "Technical problem / bug report" from "Category"
    And I fill in "Page URL" with "https://joinup.test:8080"
    And I select "Question on usage" from "Category"
    And I fill in "Subject" with "Reminder me please"
    And I enter "I will be back..." in the "Message" wysiwyg editor
    Then I press "Submit"
    And I should see the following success messages:
      | success messages                                              |
      | Your message has been submitted. Thank you for your feedback. |
