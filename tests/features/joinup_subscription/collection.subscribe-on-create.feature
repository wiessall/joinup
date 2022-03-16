@api @group-d
Feature: Subscribing to a collection after creating
  In order to subscribe my collection

  @terms
  Scenario: Check my subscription after create a new collection.
    Given users:
      | Username    | E-mail           | First name | Family name |
      | Richy Black | rb_user@test.com | RU         | User        |

    Given I am logged in as "Richy Black"
    When I go to the propose collection form
    When I fill in the following:
      | Title       | Rollerblade and Ancient Mythology                                                                      |
      | Description | The seminal work on the ancient mythologies of the primitive and classical peoples of the Rollerblade. |
      # Contact information data.
      | Name        | Larry Jaoe                                                                                              |
      | E-mail      | larry.jaoe@example.com                                                                                  |
    When I select "EU and European Policies" from "Topic"

    # Click the button to create an organisation owner.
    And I press "Add new" at the "Owner" field
    When I set the Owner type to "Company"
    And I fill in "Name" with "Acme"
    And I press "Create owner"

    When I press "Save"
    Then I should see the heading "Rollerblade and Ancient Mythology"

    Given I am logged in as a moderator
    And I go to the homepage of the "Rollerblade and Ancient Mythology" collection
    And I click "Edit" in the "Entity actions" region
    And I press "Publish"

    Then I am logged in as "Richy Black"
    And I should have the following content subscriptions:
      | Rollerblade and Ancient Mythology | discussion, document, event, news, solution |
