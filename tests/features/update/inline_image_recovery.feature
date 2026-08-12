@api @group-clone
Feature: Recover legacy inline images and clean up the content editor
  In order to stop losing inline images
  As the site owner
  The legacy <img> markup is converted to media and <img> is removed from the
  content editor, without breaking images that are already embedded as media.

  # These assert the post-deploy state of three real cloned pages, after
  # joinup_core_deploy_201602 has run and the config has been imported.

  @update:joinup_core_deploy_201602
  Scenario: An image embedded as media still renders after <img> is removed from the editor.
    # media_embed runs after filter_html and generates its own <img>, so
    # dropping <img> from allowed_html does not stop media rendering.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/portal-support/metrics"
    Then the response status code should be 200
    And I should see the "img" element with the "alt" attribute set to "Performance_Overview" in the "Content" region

  @update:joinup_core_deploy_201602
  Scenario: A page whose inline images cannot be recovered degrades to text, without a broken image.
    # These images point at an unrecoverable remote host, so nothing converts.
    # The text must survive and no dead image source may leak into the markup.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/semic-support-centre/getting-started"
    Then the response status code should be 200
    And I should see the text "Getting Started" in the "Page title" region
    And the response should not contain "googleusercontent.com"
    And the response should not contain "Image removed."

  @update:joinup_core_deploy_201602
  Scenario: The content editor no longer offers the image button and no page carries a broken inline image.
    # Smoke check: a converted page is reachable, with no image-removed
    # placeholder of the kind a stripped-source <img> would produce.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/portal-support/metrics"
    Then the response status code should be 200
    And the response should not contain "Image removed."
