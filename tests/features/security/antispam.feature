@api @javascript @antispam @group-i
Feature: As a visitor or logged-in user, when I want to post content, the form
  should be protected by Antibot & Honeypot.

  Scenario Outline: Anonymous forms.
    Given I am an anonymous user
    And I visit "<path>"
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    Examples:
      | path     |
      | /contact |

  Scenario: Authenticated users.
    Given users:
      | Username |
      | Günther  |
    And the following contact:
      | name  | ACME Inc.            |
      | email | acme.inc@example.com |
    And the following email confirmation request:
      | email                | status    |
      | acme.inc@example.com | confirmed |
    And the following collection:
      | title               | Family photos |
      | state               | published     |
      | contact information | ACME Inc.     |
    And discussion content:
      | title      | collection    | state     |
      | Let's talk | Family photos | published |
    And event content:
      | title    | collection    | state     |
      | Birthday | Family photos | published |
    And news content:
      | title       | collection    | state     |
      | Got married | Family photos | published |
    And the following contact information emails are validated:
      | ACME Inc. |

    Given I am logged in as "Günther"
    And I go to the homepage of the "Family photos" collection
    Then the form is protected by Antibot

    When I press "Join this collection"
    Then I should see the success message "You are now a member of Family photos."
    And a modal should open
    Then the form is protected by Antibot

    When I press "No thanks" in the "Modal buttons" region
    And I go to the homepage of the "Family photos" collection
    And I click "Add discussion" in the plus button menu
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    When I go to the "Let's talk" discussion
    # Comment form.
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    When I go to the "Birthday" event
    # Comment form.
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    When I go to the "Got married" news
    # Comment form.
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    When I click "Share"
    And I wait for AJAX to finish
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    When I go to the homepage of the "Family photos" collection
    And I click "ACME Inc." in the "Content" region
    Then a modal should open
    And the form is protected by Antibot
    And the form is protected by Honeypot

  Scenario Outline: Extend Honeypot & Antibot to all forms.
    Given users:
      | Username   |
      | Ransomware |
    And the following contact:
      | name  | Anti Inc.            |
      | email | anti.inc@example.com |
    And the following collection:
      | title               | Ware cat   |
      | state               | draft      |
      | contact information | Anti Inc.  |
      | author              | Ransomware |
    And the following solution:
      | title      | Ware cat   |
      | collection | Ware cat   |
      | state      | draft      |
      | author     | Ransomware |
    And the following contact information emails are validated:
      | Anti Inc. |

    Given I am logged in as "Ransomware"
    When I go to the homepage of the "Ware cat" <group type>
    When I click "Add <content type>" in the plus button menu
    And I should see the heading "Add <content type>"
    Then the form is protected by Antibot
    And the form is protected by Honeypot

    Examples:
      | content type  | group type |
      | custom page   | collection |
      | news          | collection |
      | discussion    | collection |
      | document      | collection |
      | event         | collection |
      | glossary term | collection |
      | custom page   | solution   |
      | news          | solution   |
      | discussion    | solution   |
      | document      | solution   |
      | event         | solution   |
