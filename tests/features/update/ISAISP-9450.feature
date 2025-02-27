@api @group-clone
Feature: Test removed inactive users

  @update:joinup_core_deploy_200305
  Scenario: Check users.
    Given I run the "joinup_removed_users" queue worker
    Then the following users should be deleted:
      | name            |
      | gerelia         |
      | n00ib7n0        |
      | n00ibgw4        |
      | n00ibgsk        |
      | n00ib3wc        |
      | n00ib89w        |
      | n004bwcj_htn0Tj |
      | n007i24u        |
      | n00ibeuc        |
      | n00hz16v        |
      | n00ib9aw        |
      | n00iazib        |
      | n00ib0dr        |
      | n00iazoa        |
      | n00iazh3        |
      | n00ib131        |
