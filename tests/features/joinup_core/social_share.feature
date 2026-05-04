@api @group-e
Feature: Sharing content on social networks
  As a user of the platform
  I want to share content in my social networks
  So that useful information has more visibility

  Scenario Outline: Sharing content on Facebook, X and Linkedin.
    Given the following "collection" content:
      | title           |
      | Social networks |
    And <content type> content:
      | title                 | collection      |
      | Important information | Social networks |

    When I am an anonymous user
    And I visit the "Important information" "<content type>"
    And I click "Share"
    Then I should see the heading "Share Important information on"
    And I should see the link "Facebook"
    And the share link "Facebook" should point to the "Important information" content
    And I should see the link "X"
    And the share link "X" should point to the "Important information" content
    And I should see the link "Linkedin"
    And the share link "Linkedin" should point to the "Important information" content

    Examples:
      | content type |
      | event        |
      | document     |
      | discussion   |
      | news         |
      | custom_page  |

  Scenario: Sharing solutions on social media.
    Given the following "collection" content:
      | title           |
      | Social networks |
    Given the following "solution" content:
      | title              | collection      |
      | Important solution | Social networks |

    When I am logged in as a user with the "authenticated" role
    And I go to "/solutions"
    And I click the contextual link "Share" in the "Important solution" tile

    And I should see the link "Facebook"
    And the share link "Facebook" should point to the "Important solution" content entity
    And I should see the link "X"
    And the share link "X" should point to the "Important solution" content entity
    And I should see the link "Linkedin"
    And the share link "Linkedin" should point to the "Important solution" content entity
