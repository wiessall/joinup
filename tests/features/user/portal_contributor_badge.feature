@api @group-i
Feature: Portal contributor badge
  In order to recognise active contributors to the Portal
  As a moderator
  I want users with at least 5 published contributions in the last 2 months
  to automatically receive the "Portal contributor" badge.

  Scenario: The badge is awarded based on recent eligible contributions
    Given the following users:
      | Username        | E-mail                  |
      | Alice Active    | alice@example.com       |
      | Bob Beginner    | bob@example.com         |
      | Carol Cold      | carol@example.com       |
      | Dora Drafter    | dora@example.com        |
      | Felix Custom    | felix@example.com       |
      | Greta Mixed     | greta@example.com       |
      | Hugo Commenter  | hugo@example.com        |
    And the following "collection" content:
      | title         | state     |
      | Badge Sandbox | validated |
    And the following "discussion" content:
      | title              | author          | collection    | state     | created   |
      | Active post one    | Alice Active    | Badge Sandbox | published | -1 weeks  |
      | Active post two    | Alice Active    | Badge Sandbox | published | -2 weeks  |
      | Active post three  | Alice Active    | Badge Sandbox | published | -3 weeks  |
      | Active post four   | Alice Active    | Badge Sandbox | published | -4 weeks  |
      | Active post five   | Alice Active    | Badge Sandbox | published | -5 weeks  |
      | Cold post 1        | Carol Cold      | Badge Sandbox | published | -3 months |
      | Cold post 2        | Carol Cold      | Badge Sandbox | published | -4 months |
      | Cold post 3        | Carol Cold      | Badge Sandbox | published | -5 months |
      | Cold post 4        | Carol Cold      | Badge Sandbox | published | -6 months |
      | Cold post 5        | Carol Cold      | Badge Sandbox | published | -7 months |
      | Drafter post 1     | Dora Drafter    | Badge Sandbox | draft     | -1 weeks  |
      | Drafter post 2     | Dora Drafter    | Badge Sandbox | draft     | -1 weeks  |
      | Drafter post 3     | Dora Drafter    | Badge Sandbox | draft     | -1 weeks  |
      | Drafter post 4     | Dora Drafter    | Badge Sandbox | draft     | -1 weeks  |
      | Drafter post 5     | Dora Drafter    | Badge Sandbox | draft     | -1 weeks  |
      | Mixed disc 1       | Greta Mixed     | Badge Sandbox | published | -1 weeks  |
      | Mixed disc 2       | Greta Mixed     | Badge Sandbox | published | -5 weeks  |
      | Hugo opens a topic | Hugo Commenter  | Badge Sandbox | published | -2 weeks  |
    And the following "news" content:
      | title           | headline        | author        | collection    | state     | created  |
      | Beginner news 1 | Beginner news 1 | Bob Beginner  | Badge Sandbox | published | -1 weeks |
      | Beginner news 2 | Beginner news 2 | Bob Beginner  | Badge Sandbox | published | -2 weeks |
      | Beginner news 3 | Beginner news 3 | Bob Beginner  | Badge Sandbox | published | -3 weeks |
      | Beginner news 4 | Beginner news 4 | Bob Beginner  | Badge Sandbox | published | -4 weeks |
      | Mixed news 1    | Mixed news 1    | Greta Mixed   | Badge Sandbox | published | -2 weeks |
    And the following "custom_page" content:
      | title              | author       | collection    | state     | created  |
      | Custom page one    | Felix Custom | Badge Sandbox | published | -1 weeks |
      | Custom page two    | Felix Custom | Badge Sandbox | published | -2 weeks |
      | Custom page three  | Felix Custom | Badge Sandbox | published | -3 weeks |
      | Custom page four   | Felix Custom | Badge Sandbox | published | -4 weeks |
      | Custom page five   | Felix Custom | Badge Sandbox | published | -5 weeks |
    And the following "document" content:
      | title       | author      | collection    | state     | created  |
      | Mixed doc 1 | Greta Mixed | Badge Sandbox | published | -3 weeks |
    And the following "event" content:
      | title         | author      | collection    | state     | created  |
      | Mixed event 1 | Greta Mixed | Badge Sandbox | published | -4 weeks |
    And comments:
      | message              | author          | parent             | created  |
      | First reply by Hugo  | Hugo Commenter  | Hugo opens a topic | -1 weeks |
      | Second reply by Hugo | Hugo Commenter  | Hugo opens a topic | -1 weeks |
      | Third reply by Hugo  | Hugo Commenter  | Hugo opens a topic | -1 weeks |
      | Fourth reply by Hugo | Hugo Commenter  | Hugo opens a topic | -1 weeks |
    When the eligibility queue of "portal_contributor" badge is processed
    Then user "Alice Active" should have the "portal_contributor" badge
    And user "Greta Mixed" should have the "portal_contributor" badge
    And user "Hugo Commenter" should have the "portal_contributor" badge
    But user "Bob Beginner" should not have the "portal_contributor" badge
    And user "Carol Cold" should not have the "portal_contributor" badge
    And user "Dora Drafter" should not have the "portal_contributor" badge
    And user "Felix Custom" should not have the "portal_contributor" badge
