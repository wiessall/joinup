@api @group-clone
Feature: Simple deploy hook tests for ISAICP-8386.

  Scenario: Quick links are copied over from navigation menu as disabled.
    When I go to the homepage of the "Joinup" collection
    Then I should see the following links in the "Navigation menu":
      | Overview                 |
      | Discussions              |
      | FAQ                      |
      | Joinup Roadmap           |
      | Joinup code reuse        |
      | How-To & video tutorials |
      | Federation of solutions  |
      | Glossary                 |
      | Technical documentation  |
    # All links must be disabled for anonymous.
    And I should not see the following links in the "Quick links":
      | FAQ                      |
      | Joinup Roadmap           |
      | Joinup code reuse        |
      | How-To & video tutorials |
      | Federation of solutions  |
      | Technical documentation  |

    When I am logged in as a moderator
    And I go to the homepage of the "Joinup" collection
    Then I should not see the following links in the "Quick links":
      | FAQ                      |
      | Joinup Roadmap           |
      | Joinup code reuse        |
      | How-To & video tutorials |
      | Federation of solutions  |
      | Technical documentation  |
    But I should see the text "There are not any links to show. You can manage the quick links items through the Edit menu or you can add your links through the Edit page."
    And I should see the link "Edit menu"
    And I click "Edit menu"
    # Using 'subscribers report' table name since it only uses the 'table' tag
    # as CSS selector, so there is no need to add an extra line to
    # behat.yml.dist just for this temporary test.
    Then the "subscribers report" table should contain the following columns:
      | Menu link                           |
      | FAQ (disabled)                      |
      | Joinup Roadmap (disabled)           |
      | Joinup code reuse (disabled)        |
      | How-To & video tutorials (disabled) |
      | Federation of solutions (disabled)  |
      | Technical documentation (disabled)  |
