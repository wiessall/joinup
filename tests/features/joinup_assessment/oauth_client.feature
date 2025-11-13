@api @group-c
Feature: Oauth client

  Scenario: User can request oauth2 client.
    Given users:
      | Username      | Roles     |
      | Flora Admin   | moderator |
      | Lee Requester |           |

    When I am logged in as "Lee Requester"
    Then I open the account menu
    And I click "My account"
    Then I should see the link "API client request" in the "Entity actions" region
    When I click "API client request" in the "Entity actions" region
