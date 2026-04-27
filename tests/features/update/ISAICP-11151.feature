@api @group-clone
Feature: Archive old releases.
  Old releases and their distributions that have been published for more than
  2 years should be automatically archived.

  @update:joinup_release_deploy_201200
  Scenario: Releases and their distributions published more than 2 years ago are archived.
    When I go to "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-tools/release/v03"
    Then I should see the text "Archive" in the "Page title" region
    When I go to "/collection/common-assessment-method-standards-and-specifications-camss/solution/camss-tools/distribution/camss-tools-v03-scenario-1"
    Then I should see the text "Archive" in the "Page title" region

    When I go to "/collection/interoperability-test-bed-repository/solution/interoperability-test-bed/release/1150"
    Then I should see the text "Archive" in the "Page title" region
    When I go to "/collection/interoperability-test-bed-repository/solution/interoperability-test-bed/distribution/gitb-software-docker-images-12"
    Then I should see the text "Archive" in the "Page title" region