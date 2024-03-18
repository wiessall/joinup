@api @eu_oss_mock @group-i
Feature: Test cron periodical task

  Scenario: OSS catalogue data are fetched once per day.
    Given the "eu_oss_catalogue_cron" job has run 0 times

    And I run cron
    Then the "eu_oss_catalogue_cron" job has run 1 times

    # None of the two jobs should run again in a short timeframe.
    When I run cron
    Then the "eu_oss_catalogue_cron" job has run 1 times

    # After 1 day, the "eu_oss_catalogue_cron" job should run
    # again.
    When the cron job was last run "1 day 1 minute ago"
    And I run cron
    Then the "eu_oss_catalogue_cron" job has run 2 times
