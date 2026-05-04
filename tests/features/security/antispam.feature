@api @javascript @antispam @group-h
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
    And the following "contact_information" content:
      | name      | email                | state     |
      | ACME Inc. | acme.inc@example.com | validated |
    And the following email confirmation request:
      | email                | status    |
      | acme.inc@example.com | confirmed |
    And the following "collection" content:
      | title         | contact information |
      | Family photos | ACME Inc.           |
    And the following "discussion" content:
      | title      | collection    |
      | Let's talk | Family photos |
    And the following "event" content:
      | title    | collection    |
      | Birthday | Family photos |
    And the following "news" content:
      | title       | collection    |
      | Got married | Family photos |
    And the following contact information emails are validated:
      | ACME Inc. |

    Given I am logged in as "Günther"
    And I go to the homepage of the "Family photos" collection
    When I press "Join this collection"
    Then I should see the success message "You are now a member of Family photos."
    And a modal should open
    Then the form is protected by Antibot

    When I press "Confirm" in the "Modal buttons" region
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
    And the following "contact_information" content:
      | name      | email                |
      | Anti Inc. | anti.inc@example.com |
    And the following "collection" content:
      | title    | state | contact information | author     |
      | Ware cat | draft | Anti Inc.           | Ransomware |
    And the following "solution" content:
      | title    | collection | state | author     |
      | Ware cat | Ware cat   | draft | Ransomware |
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
