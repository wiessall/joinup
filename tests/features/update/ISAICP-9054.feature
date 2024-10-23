@api @group-clone
Feature: Test removed hard bounced emails.

  @update:joinup_core_deploy_200000
  Scenario: Check users.
    Given the following users should be canceled:
      | name     |
      | garlore  |
      | n007vd09 |
      | n002xwem |
      | n003856b |
      | scaunga  |
      | idelaeve |
      | dikmese  |
      | n005ow0r |
      | n008yt6k |
      | n008zgcp |
      | n007yxdm |
      | n008y0gg |
      | n008xsof |
      | ibuffifa |
