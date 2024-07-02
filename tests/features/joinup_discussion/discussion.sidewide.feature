@api @group-h
Feature: Sitewide forum

  Background:
    Given discussion content:
      | title                    | state     | created    | changed    | published_at |
      | Blast from the past      | published | 1971-01-01 | 1971-01-01 | 1971-01-01   |
      | Old discussion           | published | 2012-12-12 | 2012-12-12 | 2012-12-12   |
      | Not that old discussion  | published | 2021-01-01 | 2021-01-01 | 2021-01-01   |
      | New but draft discussion | draft     | 2022-02-02 | 2022-02-02 | 2022-02-02   |
      | New discussion           | published | 2023-03-03 | 2023-03-03 | 2023-03-03   |
      | Quite new discussion     | published | 2024-05-05 | 2024-05-05 | 2024-05-05   |
      | Very new discussion      | published | 2024-06-06 | 2024-06-06 | 2024-06-06   |
    And user:
      | Username | Commenter |
    And comments:
      | parent                  | created    | changed    |
      | Blast from the past     | 1981-01-01 | 1981-01-01 |
      | Blast from the past     | 1983-03-03 | 1983-03-03 |
      | Old discussion          | 2012-12-12 | 2012-12-12 |
      | New discussion          | 2022-02-02 | 2022-02-02 |

  Scenario: Sitewide page lists the appropriate discussions.
    Given I go to "/discussions"
    And I should see the heading "Forum" in the "Content" region
    And I should see the text "Exchange insights, collaborate on projects and stay updated with the latest trends." in the "Content" region
    And I should see the heading "Looking for more discussions?" in the Content region
    And I should see the text "Check out discussions from all topics on our advanced search page." in the "Content" region
    And I should see the link "Go to advanced search"
    And I should see the heading "Latest discussions"
    But I should not see the heading "Most active discussions"
    And I should see the following tiles in the correct order:
      | Very new discussion     |
      | Quite new discussion    |
      | New discussion          |
      | Not that old discussion |
    # A new discussion should push "Not that old discussion" out from the most
    # recent list, but draft discussion shouldn't affect it.
    Given discussion content:
      | title                      | state     |
      | Brand new discussion       | published |
      | Brand new draft discussion | draft     |
    And I go to "/discussions"
    Then I should see the following tiles in the correct order:
      | Brand new discussion    |
      | Very new discussion     |
      | Quite new discussion    |
      | New discussion          |
    But I should not see the heading "Most active discussions"
    # Users are posting new comment to discussions.
    Given comments:
      | parent                  |
      | Not that old discussion |
      | New discussion          |
    # Leave the last comment manually to have the cache tags being invalidated
    # properly.
    Given the Commenter user leaves a comment at the "Blast from the past" discussion
    And I go to "/discussions"
    And I should see the heading "Most active discussions"
    Then in the "Most active discussions" list, I should see the following tiles in the correct order:
      | Blast from the past     |
      | New discussion          |
      | Not that old discussion |
    Then in the "Latest discussions" list, I should see the following tiles in the correct order:
      | Brand new discussion    |
      | Very new discussion     |
      | Quite new discussion    |
      | Old discussion          |
    # A new discussion getting enough comments is able to land in the most
    # active list.
    Given discussion content:
      | title         | state     |
      | Supa-dupa-new | published |
    Given comments:
      | parent        |
      | Supa-dupa-new |
      | Supa-dupa-new |
      | Supa-dupa-new |
    # Send last comment through the UI.
    Given the Commenter user leaves a comment at the "Supa-dupa-new" discussion
    And I go to "/discussions"
    Then in the "Most active discussions" list, I should see the following tiles in the correct order:
      | Supa-dupa-new           |
      | Blast from the past     |
      | New discussion          |
      | Not that old discussion |
    Then in the "Latest discussions" list, I should see the following tiles in the correct order:
      | Brand new discussion    |
      | Very new discussion     |
      | Quite new discussion    |
      | Old discussion          |
    # We only display four discussions per list.
    Given discussion content:
      | title              | state     |
      | This is the newest | published |
      | Another newest     | published |
    Given comments:
      | parent             |
      | This is the newest |
      | This is the newest |
      | This is the newest |
      | This is the newest |
    # Again, send last comment through the UI.
    Given the Commenter user leaves a comment at the "This is the newest" discussion
    Then in the "Most active discussions" list, I should see the following tiles in the correct order:
      | This is the newest      |
      | Supa-dupa-new           |
      | Blast from the past     |
      | New discussion          |
    Then in the "Latest discussions" list, I should see the following tiles in the correct order:
      | Brand new discussion    |
      | Another newest          |
      | Very new discussion     |
      | Quite new discussion    |
