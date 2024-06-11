@group-clone @api
Feature: Tests deploy hooks for the 1.82 release.

  Scenario:
    Given I am on the homepage

  @update:joinup_core_update_222222
  @update:joinup_core_post_update_123456
  @update:joinup_core_deploy_108202
  @update:joinup_core_deploy_108201
  Scenario: Test deploy hook 108202.
    Given all active memberships with accepted terms have active announcements
