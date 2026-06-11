<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Form;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\EventSubscriber\MainContentViewSubscriber;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\ShareableContentManagerInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgRoleManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base form class for share/unshare entity forms.
 */
abstract class ShareFormBase extends FormBase {

  use AutowireTrait;

  /**
   * The entity being shared.
   *
   * @var \Drupal\Core\Entity\FieldableEntityInterface
   */
  protected FieldableEntityInterface $entity;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    #[Autowire(service: 'og.membership_manager')]
    protected readonly MembershipManagerInterface $membershipManager,
    #[Autowire(service: 'og.role_manager')]
    protected readonly OgRoleManagerInterface $roleManager,
    protected readonly AccountInterface $currentUser,
    protected readonly EntityTypeBundleInfoInterface $bundleInfo,
    protected readonly CacheTagsInvalidatorInterface $invalidator,
    #[Autowire(service: 'joinup_group.shareable_content_manager')]
    protected readonly ShareableContentManagerInterface $shareableContentManager,
  ) {}

  /**
   * Returns whether the form is displayed in a modal.
   *
   * @return bool
   *   TRUE if the form is displayed in a modal.
   *
   * @todo Remove when issue #2661046 is in.
   *
   * @see https://www.drupal.org/node/2661046
   */
  protected function isModal(): bool {
    return $this->getRequest()->query->get(MainContentViewSubscriber::WRAPPER_FORMAT) === 'drupal_modal';
  }

  /**
   * Returns whether the form is being handled with an ajax request.
   *
   * @return bool
   *   TRUE if the form is being handled through an AJAX request.
   */
  protected function isAjaxForm(): bool {
    return $this->getRequest()->query->has(FormBuilderInterface::AJAX_FORM_REQUEST);
  }

}
