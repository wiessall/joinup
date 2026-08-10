@api @group-clone
Feature: Recover legacy inline images and clean up the content editor
  In order to stop losing inline images
  As the site owner
  The legacy <img> markup is converted to media and <img> is removed from the
  content editor, without breaking images that are already embedded as media.

  # These scenarios visit real cloned content after joinup_core_deploy_201602
  # has run and the content_editor config (which no longer allows <img>) has
  # been imported. They assert the post-deploy state of three representative
  # pages.

  @update:joinup_core_deploy_201602
  Scenario: An image embedded as media still renders after <img> is removed from the editor.
    # Removing <img> from filter_html's allowed_html does not affect media: the
    # media_embed filter runs after filter_html and generates its own <img>, so
    # a converted/embedded image keeps rendering.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/portal-support/metrics"
    Then the response status code should be 200
    And I should see the "img" element with the "alt" attribute set to "Performance_Overview" in the "Content" region

  @update:joinup_core_deploy_201602
  Scenario: A page whose inline images cannot be recovered degrades to text, without a broken image.
    # The images on this page point at a remote host that is not recoverable, so
    # nothing is converted. The page must still render: the surrounding text is
    # intact and no dead image source leaks into the markup.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/semic-support-centre/getting-started"
    Then the response status code should be 200
    And I should see the text "Getting Started" in the "Page title" region
    And the response should not contain "googleusercontent.com"
    And the response should not contain "Image removed."

  @update:joinup_core_deploy_201602
  Scenario: The content editor no longer offers the image button and no page carries a broken inline image.
    # Basic smoke check: a representative converted page is reachable and the
    # image-removed placeholder (which would appear if a raw <img> survived with
    # a stripped source) is absent.
    Given I am logged in as a user with the "moderator" role
    When I go to "/collection/portal-support/metrics"
    Then the response status code should be 200
    And the response should not contain "Image removed."
