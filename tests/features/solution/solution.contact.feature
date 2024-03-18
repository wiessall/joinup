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
