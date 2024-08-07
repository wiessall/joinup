<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content\Controller;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_community_content\CommunityContentHelper;
use Drupal\joinup_community_content\CommunityContentWorkflowAccessControlHandler;
use Drupal\joinup_group\Controller\GroupNodeController;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeTypeInterface;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides controllers for community content.
 */
class CommunityContentController extends GroupNodeController {

  /**
   * The node workflow access control handler.
   *
   * @var \Drupal\joinup_community_content\CommunityContentWorkflowAccessControlHandler
   */
  protected CommunityContentWorkflowAccessControlHandler $workflowAccessControlHandler;

  /**
   * Constructs a new controller instance.
   *
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access handler.
   * @param \Drupal\joinup_community_content\CommunityContentWorkflowAccessControlHandler $workflow_access_control_handler
   *   The node workflow access control handler.
   */
  public function __construct(OgAccessInterface $og_access, CommunityContentWorkflowAccessControlHandler $workflow_access_control_handler) {
    parent::__construct($og_access);
    $this->workflowAccessControlHandler = $workflow_access_control_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('og.access'),
      $container->get('joinup_community_content.community_content_workflow_access')
    );
  }

  /**
   * Handles access to the content add form through RDF entity pages.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The RDF entity for which the document entity is created.
   * @param \Drupal\node\NodeTypeInterface $node_type
   *   The type of node to be added.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for. The current user will be used if NULL.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result object.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the user entity plugin definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when the user entity type is not defined.
   */
  public function createAccess(NodeInterface $node, NodeTypeInterface $node_type, AccountInterface $account): AccessResultInterface {
    if (in_array($node_type->id(), CommunityContentHelper::BUNDLES, TRUE)) {
      assert($node instanceof GroupInterface);
      $membership = $node->getMembership((int) $account->id());
      return \Drupal::entityTypeManager()->getAccessControlHandler('node')->createAccess($node_type->id(), $account, [
        'group' => $node,
        'membership' => $membership?->id(),
      ], TRUE);
    }
    return parent::createAccess($node, $node_type, $account);
  }

}
