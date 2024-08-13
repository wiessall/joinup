@api @group-h
Feature: Contact form for solution
  User can use contact form.

  @javascript @email @uploadFiles:logo.png @loggedErrors
  Scenario: User can use "Contact us" form
    Given contact_information content:
      | name         | email                    | website url            | nid  |
      | Jocelyn Bass | jocelyn.bass@example.com | http://www.example.org | 1001 |
      | John Revoked | john@example.com         | http://www.example.org | 1002 |
      | John Deleted | deleted@example.com      | http://www.example.org | 1003 |
    And the following email confirmation request:
      | email                    | status    |
      | jocelyn.bass@example.com | confirmed |
      | john@example.com         | confirmed |
      | deleted@example.com      | confirmed |
    And collection:
      | title | Antibody development |
      | state | published            |
    And solution:
      | title               | Six exclusion advantages        |
      | description         | Separating molecules by weight. |
      | state               | published                       |
      | contact information | John Revoked                    |
      | collection          | Antibody development            |
    And user:
      | Username | Joe Hard             |
      | E-mail   | joe.hard@example.com |

    # Anonymous case.
    When I am not logged in
    And I go to the homepage of the "Six exclusion advantages" solution
    Then I should see the link "John Revoked"
    # Page was rendered, but in the meantime, contact is changed on backend.
    Given there are no preexisting log records on the site
    Given the "contact information" of "Six exclusion advantages" solution is changed to "John Deleted"
    And there should be a record of severity info in the log with description "Contact info was changed on group 'Six exclusion advantages'. Previous contact ID was '1002', new one's ID is '1003'."
    Given there are no preexisting log records
    And I click "John Revoked" in the "Content" region
    Then a modal should open
    And I should see the text "Contact us" in the "Modal title" region
    And I should see the text "Only signed in users can send a message. Please sign in or register an account on EU Login."

    When I press "Cancel" in the "Modal buttons" region
    Then the modal should be closed
    And I should not see "Only signed in users can send a message. Please sign in or register an account on EU Login." in the "Content" region

    # Delete contact info.
    Given I go to the homepage of the "Six exclusion advantages" solution
    Then I should see the link "John Deleted"
    Given the "contact information" of "Six exclusion advantages" solution is changed to "John Revoked"
    And I delete the "John Deleted" contact information
    Given there are no preexisting log records
    And I click "John Deleted" in the "Content" region
    Then I should see the following error message:
      | error messages                         |
      | Please refresh the page and try again. |
    And there should be 2 log records
    And there should be a record of severity warning in the log with description "Attempting to send message to a missing contact."
    # This is the default "page not found" error...
    And there should be a record of severity warning in the log with description "/email-contact/node/1003/email/teaser?_wrapper_format=drupal_ajax"

    # Authenticated case.
    When I am logged in as "Joe Hard"
    And I go to the homepage of the "Six exclusion advantages" solution
    Then I should see the link "John Revoked"
    # Contact is changed again after page was rendered.
    Given there are no preexisting log records on the site
    Given the "contact information" of "Six exclusion advantages" solution is changed to "Jocelyn Bass"
    And there should be 1 log record
    And there should be a record of severity info in the log with description "Contact info was changed on group 'Six exclusion advantages'. Previous contact ID was '1002', new one's ID is '1001'."
    Given there are no preexisting log records on the site
    And I click "John Revoked" in the "Content" region
    Then I should see the following error message:
      | error messages                         |
      | Please refresh the page and try again. |
    And there should be 1 log record
    And there should be a record of severity warning in the log with description "Attempting to send message to contact with ID '1002' which does not belong to a group."

    Given I reload the page
    Then I should see the link "Jocelyn Bass"
    And I click "Jocelyn Bass" in the "Content" region
    Then a modal should open

    When I fill in "Your name" with "Jonny Example"
    And I fill in "Your e-mail address" with "jonnyk"
    And I fill in "Subject" with "New reclamation about Owner"
    And I fill in "Message" with "This is reclamation test."
    And I press "Send e-mail" in the "Modal buttons" region
    Then I should see the error message "You must enter a valid e-mail address."

    When I fill in "Your e-mail address" with "example@test.test"
    And I fill in "Organization" with "My organization"
    And I select "Technical" from "Type of support"
    And I attach the file "logo.png" to "Attachment"
    And I press "Send e-mail" in the "Modal buttons" region
    Then the modal should be closed
    And I should see the success message "Your message has been sent."
    And I should be on "/collection/antibody-development/solution/six-exclusion-advantages"
    And I should see the heading "Six exclusion advantages"
    And the email sent to "jocelyn.bass@example.com" with subject "New reclamation about Owner" contains the text:
      | /collection/antibody-development/solution/six-exclusion-advantages |
      | Name: Jonny Example                                                |
      | Organization: My organization                                      |
      | Email: example@test.test                                           |
      | Type of support: Technical                                         |
      | Message: This is reclamation test.                                 |
    And the email sent to "jocelyn.bass@example.com" with subject "New reclamation about Owner" contains "logo.png" attachment
    And the email sent to "jocelyn.bass@example.com" with subject "New reclamation about Owner" contains header "Content-Type" with value "text/html"
