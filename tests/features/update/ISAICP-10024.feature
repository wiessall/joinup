@api @group-clone
Feature: Test solution documentation migration.

  @update:joinup_paragraphs_post_update_200700
  Scenario Outline:
    Given I am logged in as a moderator
    When I visit "/node/<node_id>"
    Then I should <see> the link "Documentation"
    When I visit "/node/<node_id>/edit"
    Then the response should contain "<div id=\"resources-0-item-wrapper\">"

    Examples:
      | node_id | see     |
      | 725136  | see     |
      | 726148  | not see |
      | 726483  | see     |
      | 726669  | see     |
      | 729193  | see     |
      | 729332  | see     |
      | 730730  | see     |
      | 731015  | not see |
      | 731269  | see     |
      | 731316  | see     |
      | 731408  | see     |
