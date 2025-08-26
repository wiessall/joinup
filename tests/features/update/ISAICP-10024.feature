@api @group-clone
Feature: Test solution documentation migration.

  @update:joinup_paragraphs_post_update_200601
  Scenario Outline:
    When I visit "/node/<node_id>"
    Then I should see the link "Documentation"

    Examples:
      | node_id |
      | 725136  |
      | 726148  |
      | 726483  |
      | 726669  |
      | 729193  |
      | 729332  |
      | 730730  |
      | 731015  |
      | 731269  |
      | 731316  |
      | 731408  |
