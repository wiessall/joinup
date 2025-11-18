@api @group-a
Feature:
  As a member user
  I need to be able to accept the latest Code of Conduct document.

  Background:
    Given collection content:
      | title          | closed |
      | Knowledge Hive | yes    |
    Given solution content:
      | title      | collection     |
      | Think Tank | Knowledge Hive |
    Given custom_page content:
      | title              | collection     | status    |
      | Agreement of usage | Knowledge Hive | published |
    And users:
      | Username         | E-mail                       | First name | Family name |
      | Some facilitator | some.facilitator@example.com | Some       | facilitator |
      | Cedric Hawthorne | cedric.hawthorne@example.com | Cedric     | Hawthorne   |
      | Eleanor Vaughn   | eleanor.vaughn@example.com   | Eleanor    | Vaughn      |
      | Pending member   | pending.member@example.com   | Pending    | member      |
      | Blocked member   | blocked.member@example.com   | Blocked    | member      |
    And the following collection user memberships:
      | collection     | user             | state   | roles       |
      | Knowledge Hive | Some facilitator | active  | facilitator |
      | Knowledge Hive | Cedric Hawthorne | active  |             |
      | Knowledge Hive | Eleanor Vaughn   | active  |             |
      | Knowledge Hive | Pending member   | pending |             |
      | Knowledge Hive | Blocked member   | blocked |             |
    # Set the code of conduct _after_ the creation of memberships to ensure
    # that the memberships are affected by the CoC.
    And the "Code of Conduct" setting of the "Knowledge Hive" collection is set to "Agreement of usage" "custom page"

  Scenario: User can accept the latest version of Code of Conduct
    # Before all, check that a moderator bypasses the CoC.
    Given I am logged in as a moderator
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    Given I am logged in as "Cedric Hawthorne"
    When I visit the "Knowledge Hive" Code of Conduct consent page
    Then I should see the heading "Important information"
    And I should see the following lines of text:
      | To ensure safe and respectful environment for all members of Knowledge Hive, we are introducing a Code of Conduct. |
      | If you wish to maintain your membership in Knowledge Hive, please confirm below:                                   |
    And I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    When I press "Accept"
    Then I should see the inline form error message "You must accept the Code of Conduct"

    When I check "I have read and agree to the Code of Conduct of Knowledge Hive."
    And I press "Accept"
    Then I should see the success message "Thank you for confirming your agreement to the Code of Conduct."
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    When I visit the "Knowledge Hive" Code of Conduct consent page
    Then I should get an access denied error

    # Update the CoC, check message presence.
    Given the Code of Conduct for "Knowledge Hive" collection is updated
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct to ensure safe and respectful environment for all members of Knowledge Hive. You can accept or decline it here."
    And I visit the "Think Tank" solution
    And I should see the warning message "We updated our Code of Conduct to ensure safe and respectful environment for all members of Knowledge Hive. You can accept or decline it here."
    And I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Unpublish/publish the CoC, check message presence.
    Given the Code of Conduct for "Knowledge Hive" collection is unpublished
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    Given the Code of Conduct for "Knowledge Hive" collection is published
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct"
    And I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Accept the new version of the CoC and ensure the message is gone.
    And I visit the "Knowledge Hive" Code of Conduct consent page
    When I check "accept"
    And I press "Accept"
    And I visit the "Knowledge Hive" collection
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |
    And no email has been sent to "Cedric Hawthorne"
    And I should not see the warning message "We updated our Code of Conduct"
    And I visit the "Think Tank" solution
    And I should not see the warning message "We updated our Code of Conduct"

    # Ensure previously existing member sees the message.
    Given I am logged in as "Eleanor Vaughn"
    And I visit the "Knowledge Hive" collection
    And I should see the warning message "We updated our Code of Conduct"
    And I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Ensure anonymous and blocked/pending users do not see the message.
    Given I am not logged in
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    Given I am logged in as "Pending member"
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"
    Given I am logged in as "Blocked member"
    And I visit the "Knowledge Hive" collection
    And I should not see the warning message "We updated our Code of Conduct"

  Scenario: Unsetting the CoC restores full memberships.
    Given I am logged in as "Cedric Hawthorne"
    And I visit the "Knowledge Hive" collection
    And I should see the warning message "We updated our Code of Conduct"

    # Unset the CoC, check message presence.
    Given the "Code of Conduct" setting of the "Knowledge Hive" collection is unset
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    But I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

  Scenario: Facilitators are not affected by the CoC.
    Given I am logged in as "Some facilitator"
    And I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Ensure facilitators can see the CoC page.
    When I visit the "Knowledge Hive" Code of Conduct consent page
    Then I should see the heading "Important information"

    # Ensure facilitators see the links regardless of the status of the CoC.
    Given the Code of Conduct for "Knowledge Hive" collection is unpublished
    And I visit the "Knowledge Hive" collection
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |
    Given the Code of Conduct for "Knowledge Hive" collection is published
    And I visit the "Knowledge Hive" collection
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

  Scenario: Changing the membership moderation of collections affect members.
    Given I am logged in as "Cedric Hawthorne"
    And I visit the "Knowledge Hive" collection
    And I should see the warning message "We updated our Code of Conduct"

    # Creating a new unpublished CoC should not affect the members.
    Given the "Knowledge Hive" "collection" has a new "draft" revision with "open" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct"
    Then I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Changing the membership moderation between published revisions affect the
    # memberships.
    Given the "Knowledge Hive" "collection" has a new "published" revision with "open" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Changing the membership moderation between unpublished revisions does not
    # affect the memberships. Change to closed to check the effect.
    Given the "Knowledge Hive" "collection" has a new "draft" revision with "closed" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Not changing the membership moderation between published revisions does
    # not affect the memberships.
    Given the "Knowledge Hive" "collection" has a new "published" revision with "open" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Change again to closed with an inbetween unpublished revision to check
    # that full memberships are revoked.
    Given the "Knowledge Hive" "collection" has a new "draft" revision with "closed" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Given the "Knowledge Hive" "collection" has a new "published" revision with "closed" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct"
    Then I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Regression test: Check that the same apply if the collection is directly
    # changed from published to published.
    Given the "Knowledge Hive" "collection" has a new "published" revision with "open" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |
    Given the "Knowledge Hive" "collection" has a new "published" revision with "closed" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should see the warning message "We updated our Code of Conduct"
    Then I should not see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Accept the CoC to ensure that the message is gone.
    When I visit the "Knowledge Hive" Code of Conduct consent page
    And I check "accept"
    And I press "Accept"
    Then I should see the success message "Thank you for confirming your agreement to the Code of Conduct."
    And I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # Regression test: Check changing the workflow state of the collection
    # to open and then to closed again, will not remove the full membership.
    Given the "Knowledge Hive" "collection" has a new "published" revision with "open" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    Given the "Knowledge Hive" "collection" has a new "published" revision with "closed" membership moderation
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"
    Then I should see the following links:
      | Add discussion |
      | Add document   |
      | Add event      |
      | Add news       |
      | Add solution   |

    # When a collection is archived, the CoC message should not show.
    And the workflow state of the "Knowledge Hive" content is changed to "archived"
    When I visit the "Knowledge Hive" collection
    Then I should not see the warning message "We updated our Code of Conduct"

  Scenario: Code of conduct pages cannot be moved to another group.
    Given I am logged in as a moderator
    And I visit the "Knowledge Hive" collection
    And I click "Manage content"
    And I select the "Agreement of usage" row
    And I select "Move to other group" from "Action"
    And I press "Apply to selected items"
    And I fill in "Select the destination collection or solution" with "Portal support"
    And I press "Apply"
    And I wait for the batch process to finish
    Then I should see the error message "Custom page Agreement of usage was not moved because it is set as the code of conduct."
