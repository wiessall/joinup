@api @group-g
Feature: Subscribing to community content in solutions
  As a member of a solution
  I want to receive a periodic digest listing newly published content
  So that I can stay informed

  Background:
    Given the following "collection" content:
      | title    | state     |
      | Bulgaria | published |
    Given the following "solution" content:
      | title                | state     | collection |
      | Products of Bulgaria | published | Bulgaria   |
      | Cities of Bulgaria   | published | Bulgaria   |
    And the following users:
      | Username | E-mail            | First name | Family name  | Notification frequency | Status    |
      | hristo   | hristo@example.bg | Hristo     | Draganov     | daily                  | active    |
      | bisera   | bisera@example.bg | Bisera     | Kaloyancheva | weekly                 | active    |
      | kalin    | kalin@primer.bg   | Kalin      | Antov        | monthly                | active    |
      | aleks    | aleks@primer.bg   | Aleksandar | Begov        | monthly                | blocked   |

    And the following solution user memberships:
      | solution             | user   | roles       |
      | Products of Bulgaria | hristo |             |
      | Products of Bulgaria | bisera |             |
      | Products of Bulgaria | kalin  |             |
      | Cities of Bulgaria   | hristo |             |
      | Cities of Bulgaria   | bisera |             |
      | Cities of Bulgaria   | kalin  | facilitator |
      | Cities of Bulgaria   | aleks  |             |
    And the following solution content subscriptions:
      | solution             | user   | subscriptions                          |
      | Products of Bulgaria | hristo | discussion, event, news, distribution  |
      | Products of Bulgaria | bisera | discussion, document, news, release    |
      | Products of Bulgaria | kalin  | document, event, distribution, release |
      | Cities of Bulgaria   | hristo | document, event                        |
      | Cities of Bulgaria   | bisera | discussion, event, news                |
      | Cities of Bulgaria   | kalin  | discussion, document, news             |
      | Cities of Bulgaria   | aleks  | discussion, document, news             |
    And all message digests have been delivered

  Scenario: Receive a digest of content that is published in my solutions
    # Start on clean.
    Given I mark all emails as read

    And the following "discussion" content:
      | title      | body                      | solution             | state     | author |
      | Duck liver | Rich buttery and delicate | Products of Bulgaria | published | hristo |
      | Sofia      | Grows without aging       | Cities of Bulgaria   | published | kalin  |
      | Ruse       | Little Vienna             | Cities of Bulgaria   | proposed  | kalin  |
    And the following "document" content:
      | title           | field_paragraphs_body  | solution             | state     | author |
      | Canned cherries | Sour cherries for pies | Products of Bulgaria | published | bisera |
      | Plovdiv         | Seven hills            | Cities of Bulgaria   | published | hristo |
    And the following "event" content:
      | title           | field_paragraphs_body | solution             | state     | author | start date          | end date            |
      | Sunflower seeds | A tasty snack         | Products of Bulgaria | published | bisera | 2019-11-28T11:12:13 | 2019-11-28T11:12:13 |
      | Varna           | Summer capital        | Cities of Bulgaria   | draft     | kalin  | 2019-12-05T12:00:00 | 2019-12-15T12:00:00 |
      | Stara Zagora    | Historic              | Cities of Bulgaria   | published | hristo | 2020-01-18T18:30:00 | 2020-01-19T00:00:00 |
    And the following "news" content:
      | title    | body                        | solution             | state     | author |
      | Rose oil | A widely used essential oil | Products of Bulgaria | published | bisera |
      | Burgas   | City of dreams              | Cities of Bulgaria   | published | hristo |
    And the following "release" content:
      | title       | release number | release notes   | is version of        | state     | published at     |
      | Spring 2021 | 2021/03        | Blooming pears. | Products of Bulgaria | published | 2021-03-21 00:00 |
    And the following "distribution" content:
      | title                 | description    | solution             | release     | state     |
      # A distribution linked directly to the solution.
      | Full list of products | There are many | Products of Bulgaria |             | published |
      # A distribution linked to a release.
      | Spring discounts      | Get them now   |                      | Spring 2021 | published |

    Then the daily group content subscription digest for hristo should match the following messages:
      | Duck liver            |
      | Sunflower seeds       |
      | Rose oil              |
      | Plovdiv               |
      | Stara Zagora          |
      | Full list of products |
      | Spring discounts      |
    And the weekly group content subscription digest for bisera should match the following message:
      | Duck liver      |
      | Canned cherries |
      | Rose oil        |
      | Sofia           |
      | Stara Zagora    |
      | Burgas          |
      | Spring 2021     |
    And the monthly group content subscription digest for kalin should match the following message:
      | Canned cherries       |
      | Sunflower seeds       |
      | Sofia                 |
      | Plovdiv               |
      | Burgas                |
      | Spring 2021           |
      | Full list of products |
      | Spring discounts      |

    # Check that only the user's chosen frequency is digested.
    But the weekly digest for hristo should not contain any messages
    And the monthly digest for hristo should not contain any messages
    And the daily digest for bisera should not contain any messages
    And the monthly digest for bisera should not contain any messages
    And the daily digest for kalin should not contain any messages
    And the weekly digest for kalin should not contain any messages

    # The digest should not include news about content that is not published.
    And the daily group content subscription digest for hristo should not contain the following messages:
      | Varna |
    And the weekly group content subscription digest for bisera should not contain the following message:
      | Ruse  |
      | Varna |
    And the monthly group content subscription digest for kalin should not contain the following message:
      | Ruse |

    # Publish an existing unpublished community content. It should be included
    # in the next digest.
    When the workflow state of the "Ruse" content is changed to "published"

    Then the weekly group content subscription digest for bisera should include the following message:
      | Ruse |
    And the monthly group content subscription digest for kalin should include the following message:
      | Ruse |

    # Check that the messages are formatted correctly.
    Given all message digests have been delivered
    And the group content subscription digest sent to hristo contains the following sections:
      | title                 |
      | Cities of Bulgaria    |
      | Plovdiv               |
      | Stara Zagora          |
      | Products of Bulgaria  |
      | Duck liver            |
      | Full list of products |
      | Rose oil              |
      | Spring discounts      |
      | Sunflower seeds       |
    And the content subscription digest sent to hristo should have the subject "Interoperable Europe Portal: Daily digest message"

    And the group content subscription digest sent to bisera contains the following sections:
      | title                |
      | Cities of Bulgaria   |
      | Burgas               |
      | Sofia                |
      | Stara Zagora         |
      | Products of Bulgaria |
      | Canned cherries      |
      | Rose oil             |
      | 21 March 2021        |
      | Spring 2021          |
    And the content subscription digest sent to bisera should have the subject "Interoperable Europe Portal: Weekly digest message"

    And the group content subscription digest sent to kalin contains the following sections:
      | title                 |
      | Cities of Bulgaria    |
      | Burgas                |
      | Plovdiv               |
      | Sofia                 |
      | Products of Bulgaria  |
      | Canned cherries       |
      | Full list of products |
      | Spring 2021           |
      | Spring discounts      |
      | Sunflower seeds       |
    And the content subscription digest sent to kalin should have the subject "Interoperable Europe Portal: Monthly digest message"

    # No message for aleks@primer.bg (blocked).
    And 4 e-mails should have been sent to recipients "kalin@primer.bg, hristo@example.bg, bisera@example.bg"

    # Clean out the message queue for the next test.
    And I mark all emails as read

    # Check that if community content is published a second time it is not
    # included in the next digest.
    When the workflow state of the "Ruse" content is changed to "draft"
    Then the weekly group content subscription digest for bisera should not contain the following message:
      | Ruse |
    And the monthly group content subscription digest for kalin should not contain the following message:
      | Ruse |
    When the workflow state of the "Ruse" content is changed to "published"
    Then the weekly group content subscription digest for bisera should not contain the following message:
      | Ruse |
    And the monthly group content subscription digest for kalin should not contain the following message:
      | Ruse |
