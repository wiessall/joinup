<?php

declare(strict_types=1);

namespace Drupal\joinup_subscription\ParamConverter;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_subscription\JoinupSubscriptionsHelper;
use Drupal\og\MembershipManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\Routing\Route;

/**
 * Converts route params of type 'group_unsubscribe_types'.
 *
 * This converter runs after sparql.paramconverter and paramconverter.entity, so
 * the group and user parameters are already converted at this point.
 */
class GroupUnsubscribeTypesConverter implements ParamConverterInterface {

  /**
   * The OG membership manager service.
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * Constructs a new parameter converter service instance.
   *
   * @param \Drupal\og\MembershipManagerInterface $membership_manager
   *   The OG membership manager service.
   */
  public function __construct(MembershipManagerInterface $membership_manager) {
    $this->membershipManager = $membership_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return isset($definition['type']) && $definition['type'] === 'group_unsubscribe_types';
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($definition['user_param']) || !isset($defaults[$definition['user_param']]) || !$defaults[$definition['user_param']] instanceof UserInterface) {
      throw new \Exception("The 'group_unsubscribe_types' param type requires a valid 'user_param' option");
    }
    if (empty($definition['group_param']) || !isset($defaults[$definition['group_param']]) || !$defaults[$definition['group_param']] instanceof GroupInterface) {
      throw new \Exception("The 'group_unsubscribe_types' param type requires a valid 'group_param' option");
    }

    $account = $defaults[$definition['user_param']];
    $group = $defaults[$definition['group_param']];

    // The user is not member of this group.
    if (!$this->membershipManager->getMembership($group, $account->id())) {
      return NULL;
    }

    $items = [];
    foreach (array_filter(explode(',', trim($value))) as $part) {
      // Announcements are a special case.
      if (!str_contains($part, ':')) {
        if ($part !== 'announcements') {
          return NULL;
        }
        $items[$part] = $part;
        continue;
      }
      [$entity_type_id, $bundle] = explode(':', $part);
      if (!isset(JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES[$group->bundle()])) {
        return NULL;
      }
      if (!isset(JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES[$group->bundle()][$entity_type_id]) || !in_array($bundle, JoinupSubscriptionsHelper::SUBSCRIPTION_BUNDLES[$group->bundle()][$entity_type_id])) {
        return NULL;
      }
      $items[$entity_type_id][] = $bundle;
    }
    return $items;
  }

}
