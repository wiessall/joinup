@api @group-clone
Feature: Simple deploy hook tests for ISAICP-8386.

  Scenario: Redirect from About to Overview.
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
    And I should see the following links in the "Quick links":
      | FAQ                      |
      | Joinup Roadmap           |
      | Joinup code reuse        |
      | How-To & video tutorials |
      | Federation of solutions  |
      | Technical documentation  |
