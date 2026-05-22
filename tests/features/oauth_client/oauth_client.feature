@api @group-c
Feature: Oauth client

  Scenario: User can request oauth2 clients.
    Given the following users:
      | Username      | Roles     |
      | Flora Admin   | moderator |
      | Lee Requester |           |

    When I am logged in as "Lee Requester"
    Then I open the account menu
    And I click "My account"
    Then I should see the link "API clients" in the "Entity actions" region
    And I should see the link "API client request" in the "Entity actions" region

    When I click "API client request" in the "Entity actions" region
    And I click "Request client"
    And I fill in "Label" with "Super duper API"
    And I fill in "Request reason" with "I really need this, I don't know why yet but I need it"
    And I press "Save"
    Then I should see the success message "New OAuth client request Super duper API has been created."

    And 2 e-mail should have been sent
    And the following email should have been sent:
      | recipient          | Flora Admin                                                                |
      | subject            | New OAuth client request from Lee Requester on Interoperable Europe Portal |
      | signature_required | no                                                                         |
      | body               | A new OAuth client request has been submitted by Lee Requester             |

    Given I am logged in as "Flora Admin"
    And I visit "/admin/config/services/consumer/oauth-client-request"
    When I click "Delete" in the "Super duper API" row
    And I should see "Are you sure you want to delete the OAuth client request Super duper API?"
    When I press "Delete"

  Scenario: Moderation action triggers a notification.
    Given the following users:
      | Username       | Roles     |
      | Test Requester | moderator |
      | Lee Requester  |           |
    And oauth client requests:
      | label             | request_reason        | user          | type        |
      | Test API Client 1 | For automated testing | Lee Requester | assessments |
      | Test API Client 2 | For automated testing | Lee Requester | assessments |

    Given I am logged in as "Test Requester"
    And I go to the edit page of the "Test API Client 1" oauth client request
    When I select the radio button "Rejected"
    And I fill in "Reject reason" with "You are not permitted to do it"
    And I press "Save"
    Then 1 e-mail should have been sent

    And the following email should have been sent:
      | recipient          | Lee Requester                                                                                                  |
      | subject            | Your OAuth client request has been rejected                                                                    |
      | signature_required | no                                                                                                             |
      | body               | We regret to inform you that your OAuth client request, Test API Client 1, could not be approved at this time. |

    Given I mark all emails as read
    When I go to the edit page of the "Test API Client 2" oauth client request
    And I select the radio button "Active"
    And I press "Save"
    Then 1 e-mail should have been sent
    And the following email should have been sent:
      | recipient          | Lee Requester                                                                                      |
      | subject            | Your OAuth client request has been approved                                                        |
      | signature_required | no                                                                                                 |
      | body               | We are pleased to inform you that your OAuth client request, Test API Client 2, has been approved. |

    When I delete the "Test API Client 1" oauth client request
    When I delete the "Test API Client 2" oauth client request
