@api @group-g
Feature: Contact form for solution
  User can use contact form.

  @javascript @email @uploadFiles:logo.png
  Scenario: User can use "Contact us" form
    Given contact:
      | name        | Jocelyn Bass             |
      | email       | jocelyn.bass@example.com |
      | website url | http://www.example.org   |
    And the following email confirmation request:
      | email                    | status    |
      | jocelyn.bass@example.com | confirmed |
    And collection:
      | title | Antibody development |
      | state | published            |
    And solution:
      | title               | Six exclusion advantages        |
      | description         | Separating molecules by weight. |
      | state               | published                       |
      | contact information | Jocelyn Bass                    |
      | collection          | Antibody development            |
    And user:
      | Username | Joe Hard             |
      | E-mail   | joe.hard@example.com |

    # Anonymous case.
    When I am not logged in
    And I go to the homepage of the "Six exclusion advantages" solution
    And I click "Jocelyn Bass" in the "Content" region
    Then a modal should open
    And I should see the text "Contact us" in the "Modal title" region
    And I should see the text "Only signed in users can send a message. Please sign in or register an account on EU Login."

    When I press "Cancel" in the "Modal buttons" region
    Then the modal should be closed
    And I should not see "Only signed in users can send a message. Please sign in or register an account on EU Login." in the "Content" region

    # Authenticated case.
    When I am logged in as "Joe Hard"
    And I go to the homepage of the "Six exclusion advantages" solution
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

  @terms @javascript @email
  Scenario: User receives an mail about contact validation.
    Given owner:
      | name      | type               |
      | Rotterdam | National authority |
    And user:
      | Username    | Pally Rapamichael      |
      | First name  | Pally                  |
      | Family name | Rapamichael            |
      | E-mail      | p.papamichael@pally.de |
    And collection:
      | title | Antibody development |
      | state | published            |
    And solution:
      | title         | Six exclusion advantages                          |
      | abstract      | abstract for six                                  |
      | description   | Separating molecules by weight.                   |
      | state         | published                                         |
      | topic         | Demography, E-inclusion                           |
      | owner         | Rotterdam                                         |
      | solution type | Non-binding Instrument, Service Discovery Service |
      | collection    | Antibody development                              |
    And CAS users:
      | Username           | E-mail         | Password |
      | Iodine Maccalariat | iodine@ankh.am | 10d1ne   |

    When I am logged in as a moderator
    And I go to the edit form of the "Six exclusion advantages" solution
    And I fill in "Name" with "Invisible Cat"
    And I fill in "E-mail" with "invisible.cat@xpto.com"
    And I press "Create contact information"
    And I press "Publish"
    Then I should see the success message "A new confirmation message has been sent to invisible.cat@xpto.com."
    And the email sent to "invisible.cat@xpto.com" with subject "Joinup - Please validate your contact details" contains the text:
      | Dear Joinup user,                                                                                                                                                           |
      | As owner/facilitator, we’re improving your privacy by replacing the publicly visible contact email information with a contact form that users/members can use to reach you. |
      | Please confirm your email address invisible.cat@xpto.com, which is set in your contact information for the following:                                                       |
      | Solution: Six exclusion advantages                                                                                                                                          |
      | If the link doesn't work, just copy this URL to your browser                                                                                                                |
      | If you do not validate your email within the next 5 days, you will not be able to receive any messages from users/members.                                                  |
      | If you think this action is not clear or not due, please contact Joinup Support at https://joinup.ec.europa.eu/contact.                                                     |
      | Best regards,                                                                                                                                                               |
      | The Joinup Team                                                                                                                                                             |
    And the email sent to "invisible.cat@xpto.com" with subject "Joinup - Please validate your contact details" contains header "Content-Type" with value "text/html"
