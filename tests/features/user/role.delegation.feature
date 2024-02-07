@api @group-f
Feature: Tests related to the administrator - product owner role.

  Scenario: The administrator is able to delegate any role to any user.
    Given users:
      | Username        | Roles           |
      | Product owner   | Product owner   |
      | Moderator       | Moderator       |
      | Developer       | Developer       |
      | Licence manager | Licence manager |
      | User 1          |                 |

    When I am logged in as "Product owner"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "User 1" row
    And the "Roles" checkboxes should contain the following options:
      | Licence manager |
      | Moderator       |
      | Product owner   |
      | Developer       |

    Given I am logged in as "Moderator"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "User 1" row
    And the "Roles" checkboxes should contain the following options:
      | Licence manager |

    Given I am logged in as "Developer"
    And I click "People" in the "Administration toolbar" region
    And I click "Edit" in the "User 1" row
    And the "Roles" checkboxes should contain the following options:
      | Developer       |
