@api @group-i
Feature: User role management
  As a moderator I must be able to assign roles to users.

  Background:
    Given users:
      | Username        | Roles           | E-mail                    |
      | Administrator   | Administrator   | product.owner@example.com |
      | Rick Rolls      | Moderator       | rick.rolls@example.com    |
      | Licence manager | Licence manager | rick.rolls@example.com    |
      | Awesome dev     | Developer       | awesome.dev@example.com   |
      | Nibby Noob      |                 | nicky.noob@example.com    |
      | Ursus           |                 |                           |

  Scenario: Verify user edit roles available options.
    When I am logged in as "Administrator"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "Ursus" row
    And the "Roles" checkboxes should contain the following options:
      | Administrator              |
      | Assessment moderator       |
      | Assessment reporter        |
      | Developer                  |
      | Interoperability validator |
      | Licence manager            |
      | Moderator                  |
      | Translator                 |
      | OSS Catalogue Manager      |

    Given I am logged in as "Rick Rolls"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "Ursus" row
    And the "Roles" checkboxes should contain the following options:
      | Assessment reporter   |
      | Assessment moderator  |
      | Licence manager       |
      | OSS Catalogue Manager |
      | Translator            |

    Given I am logged in as "Awesome dev"
    And I go to "/admin/people"
    And I click "Edit" in the "Ursus" row
    And the "Roles" checkboxes should contain the following options:
      | Administrator              |
      | Assessment moderator       |
      | Assessment reporter        |
      | Authenticated user         |
      | Developer                  |
      | Licence manager            |
      | Moderator                  |
      | OSS Catalogue Manager      |
      | Translator                 |
      | Interoperability validator |

  Scenario: Verify options available to the moderator.
    Given I am logged in as "Rick Rolls"
    And I am on the homepage
    And I click "People"

    Then the "Action" field should contain the "Add the Licence manager role to the selected user(s), Remove the Licence manager role from the selected user(s)" options
    And the available options in the "Action" select should not include the "Add the Moderator role to the selected user(s), Remove the Moderator role from the selected user(s), Add the Administrator role to the selected user(s), Remove the Administrator role from the selected user(s)" options

  Scenario: A product owner can assign the moderator role.
    Given I am logged in as "Administrator"
    And I am on the homepage
    And I click "People"

    When I fill in "Name or email contains" with "Nibby Noob"
    And I press "Filter"
    # Select user and assign role
    And I check "Nibby Noob"
    And I select "Add the Moderator role to the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Add the Moderator role to the selected user(s) was applied to 1 item."
    And I should see the success message "The user has been notified that their account has been updated."
    And the following email should have been sent:
      | recipient | Nibby Noob                                                                                                                     |
      | subject   | The Portal Support Team updated your account for you at Interoperable Europe Portal                                            |
      | body      | A moderator has edited your user profile on Interoperable Europe Portal. Please check your profile to verify the changes done. |

    Given I am on the homepage
    When I mark all emails as read
    And I click "People"

    When I click "Edit" in the "Ursus" row
    And I fill in "First name" with "Ur"
    And I fill in "Family name" with "Sus"
    And I press "Save"
    Then I should see the success message "The changes have been saved."
    But I should not see the success message "The user has been notified that their account has been updated."
    And 0 e-mails should have been sent

  Scenario: A moderator can assign and remove the Licence manager role.
    Given licence content:
      | title        | description               | licence type  |
      | Open licence | Licence agreement details | Public domain |

    And I am logged in as "Rick Rolls"
    And I am on the homepage
    And I click "People"

    When I fill in "Name or email contains" with "Nibby Noob"
    And I press "Filter"
    # Select user and assign role
    And I check "Nibby Noob"
    And I select "Add the Licence manager role to the selected user(s)" from "Action"
    And I press "Apply to selected items"
    Then I should see the success message "Add the Licence manager role to the selected user(s) was applied to 1 item."
    And I should see the success message "The user has been notified that their account has been updated."
    And the following email should have been sent:
      | recipient | Nibby Noob                                                                                                                     |
      | subject   | The Portal Support Team updated your account for you at Interoperable Europe Portal                                            |
      | body      | A moderator has edited your user profile on Interoperable Europe Portal. Please check your profile to verify the changes done. |

    When I am logged in as "Nibby Noob"
    And I go to the edit form of the "Open licence" licence
    And I fill in "Title" with "Closed licence"
    And I press "Save"
    Then I should see the heading "Closed licence"
