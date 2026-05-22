@api @group-h
Feature: Unsubscribe from collections
  In order to reduce the amount of notifications I receive
  As a user of the website
  I need to be able to easily unsubscribe from groups or group content types.

  Background:
    Given user:
      | Username | Eric Cartman             |
      | E-mail   | eric.cartman@example.com |
    And the following "collection" content:
      | title                | state     |
      | Southpark elementary | published |
      | Kenny's house        | draft     |
    And the following collection user memberships:
      | collection           | user         | roles  |
      | Southpark elementary | Eric Cartman | member |
      | Kenny's house        | Eric Cartman | owner  |
    And the following collection content subscriptions:
      | collection           | user         | subscriptions                     |
      | Southpark elementary | Eric Cartman | discussion, document, event, news |
      | Kenny's house        | Eric Cartman | discussion, document, event, news |

  Scenario: Unsubscribe from all groups
    Given the following "collection" content:
      | title          | state    |
      | Koon's hideout | proposed |
    And the following collection user memberships:
      | collection     | user         | roles       |
      | Koon's hideout | Eric Cartman | facilitator |
    And the following collection content subscriptions:
      | collection     | user         | subscriptions                     |
      | Koon's hideout | Eric Cartman | discussion, document, event, news |
    Given the following "solution" content:
      | title              | state     |
      | Poor man's Shelter | published |
      | Anna's house       | draft     |
      | Greg's foxhole     | proposed  |
    And the following solution user memberships:
      | solution           | user         | roles       |
      | Poor man's Shelter | Eric Cartman | member      |
      | Anna's house       | Eric Cartman | owner       |
      | Greg's foxhole     | Eric Cartman | facilitator |
    And the following solution content subscriptions:
      | solution           | user         | subscriptions                     |
      | Poor man's Shelter | Eric Cartman | discussion, document, event, news |
      | Anna's house       | Eric Cartman | discussion, document, event, news |
      | Greg's foxhole     | Eric Cartman | discussion, document, event, news |

    Given I am logged in as "Eric Cartman"
    When I click "My subscriptions"

    Then I should see the link "Unsubscribe from all"
    And the following content subscriptions should be selected:
      | Southpark elementary | discussion, document, event, news, announcements |
      | Kenny's house        | discussion, document, event, news, announcements |
      | Koon's hideout       | discussion, document, event, news, announcements |
      | Poor man's Shelter   | discussion, document, event, news, announcements |
      | Anna's house         | discussion, document, event, news, announcements |
      | Greg's foxhole       | discussion, document, event, news, announcements |

    When I click "Unsubscribe from all"
    Then I should see the heading "Unsubscribe from all?"

    And I should see the following lines of text:
      | Are you sure you want to unsubscribe from all collections and/or solutions? You will stop receiving news and updates, including the pending memberships, from the following: |
      | Collections                                                                                                                                                                  |
      | Southpark elementary                                                                                                                                                         |
      | Kenny's house                                                                                                                                                                |
      | Koon's hideout                                                                                                                                                               |
      | Solutions                                                                                                                                                                    |
      | Poor man's Shelter                                                                                                                                                           |
      | Anna's house                                                                                                                                                                 |
      | Greg's foxhole                                                                                                                                                               |

    When I click "Cancel"
    Then I should see the heading "Subscription settings"

    When I click "Unsubscribe from all"
    And I press "Confirm"
    And I wait for the batch process to finish

    # Checks partially the success messages so it can work like the step that asserts 'lines of text' above.
    Then I should see the following success messages:
      | success messages                                            |
      | You will no longer receive notifications for the following: |
      | Collections                                                 |
      | Kenny's house                                               |
      | Koon's hideout                                              |
      | Southpark elementary                                        |
      | Solutions                                                   |
      | Poor man's Shelter                                          |
      | Anna's house                                                |
      | Greg's foxhole                                              |

    And the following content subscriptions should be selected:
      | Kenny's house        |  |
      | Koon's hideout       |  |
      | Southpark elementary |  |
      | Poor man's Shelter   |  |
      | Anna's house         |  |
      | Greg's foxhole       |  |

    And I should not see the link "Unsubscribe from all"

    And I should have the following content subscriptions:
      | Southpark elementary |  |
      | Kenny's house        |  |
      | Koon's hideout       |  |
      | Poor man's Shelter   |  |
      | Anna's house         |  |
      | Greg's foxhole       |  |

  Scenario: Unsubscribe from a specific group.
    Given the following "solution" content:
      | title                  | state     |
      | Not member in this one | published |

    Given I am logged in as "Eric Cartman"

    When I visit the unsubscribe from "Southpark elementary" collection form
    Then I should see the heading "Unsubscribe from Southpark elementary collection?"
    And I should see "Are you sure you want to unsubscribe from Southpark elementary collection? You will stop receiving news and updates, including the pending memberships, from this collection."

    When I press "Confirm"
    Then I should see the success message "You will no longer receive notifications from Southpark elementary collection."
    And the following content subscriptions should be selected:
      | Southpark elementary |                                                  |
      | Kenny's house        | discussion, document, event, news, announcements |

    When I visit the unsubscribe from "Kenny's house" collection form by content types:
      | Document      |
      | Event         |
      | Announcements |
    Then I should see the heading "Unsubscribe from Kenny's house collection?"
    And I should see the following lines of text:
      | Are you sure you want to unsubscribe from the Kenny's house collection content types? You will stop receiving updates for the following types of content from this collection: |
      | documents                                                                                                                                                                      |
      | events                                                                                                                                                                         |
      | announcements                                                                                                                                                                  |

    When I press "Confirm"
    Then I should see the success message "You will no longer receive notifications from Kenny's house collection, for: documents, events, announcements."
    And the following content subscriptions should be selected:
      | Southpark elementary |                  |
      | Kenny's house        | discussion, news |

    When I visit the unsubscribe from "Kenny's house" collection form by content types:
      | News |
    And I press "Confirm"
    Then I should see the success message "You will no longer receive notifications from Kenny's house collection, for: news."
    And the following content subscriptions should be selected:
      | Southpark elementary |            |
      | Kenny's house        | discussion |

    # The user tries to unsubscribe from a group where it's not member.
    When I visit the unsubscribe from "Not member in this one" solution form
    Then the response status code should be 404

    # Passing malformed type.
    When I visit the unsubscribe from "Southpark elementary" collection form with "foo" route parameter
    Then the response status code should be 404

    # Collection is not group content.
    When I visit the unsubscribe from "Southpark elementary" collection form with "node:collection" route parameter
    Then the response status code should be 404

    # Cannot subscribe to custom pages.
    When I visit the unsubscribe from "Southpark elementary" collection form with "node:custom_page,node:solution" route parameter
    Then the response status code should be 404

    # Add the user to the solution.
    Given the following solution user memberships:
      | solution               | user         | roles  |
      | Not member in this one | Eric Cartman | member |
    And the following solution content subscriptions:
      | solution               | user         | subscriptions                     |
      | Not member in this one | Eric Cartman | discussion, document, event, news |

    # Solution is not solution group content.
    When I visit the unsubscribe from "Not member in this one" solution form with "node:solution" route parameter
    Then the response status code should be 404
