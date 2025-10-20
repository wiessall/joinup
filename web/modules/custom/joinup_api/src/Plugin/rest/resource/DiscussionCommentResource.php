<?php

declare(strict_types=1);

namespace Drupal\joinup_api\Plugin\rest\resource;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\comment\Entity\Comment;
use Drupal\rest\Attribute\RestResource;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides a resource for posting comments to discussions.
 */
#[RestResource(
  id: 'discussion_comment',
  label: new TranslatableMarkup('Discussion Comment'),
  uri_paths: [
    'create' => '/api/discussion/{discussion_id}/comment',
  ],
)]
class DiscussionCommentResource extends ResourceBase {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountProxyInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function permissions(): array {
    return ['post comments'];
  }

  /**
   * Responds to POST requests.
   *
   * Creates a new comment on a discussion node.
   *
   * @param int $discussion_id
   *   The discussion node ID.
   * @param array $data
   *   The request payload.
   *
   * @return \Drupal\rest\ModifiedResourceResponse
   *   The HTTP response object.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\HttpException
   *   Throws exception expected.
   */
  public function post(int $discussion_id, array $data): ModifiedResourceResponse {
    // Check if user is authenticated (OAuth2 token validated by simple_oauth).
    if ($this->currentUser->isAnonymous()) {
      throw new AccessDeniedHttpException('Authentication required. Please provide a valid OAuth2 token.');
    }

    // Validate required fields.
    if (empty($data['subject']) || empty($data['comment_body'])) {
      throw new BadRequestHttpException('Missing required fields: subject and comment_body are required.');
    }

    // Validate text format.
    $allowed_formats = ['plain_text', 'basic_html'];
    $format = $data['format'] ?? 'plain_text';
    if (!in_array($format, $allowed_formats, TRUE)) {
      throw new BadRequestHttpException('Invalid text format. Allowed formats: ' . implode(', ', $allowed_formats));
    }

    // Load the discussion node.
    $nodeStorage = $this->entityTypeManager->getStorage('node');
    $discussion = $nodeStorage->load($discussion_id);

    if (!$discussion) {
      throw new NotFoundHttpException('Discussion not found.');
    }

    // Verify it's a discussion node type.
    if ($discussion->bundle() !== 'discussion') {
      throw new BadRequestHttpException('The specified node is not a discussion.');
    }

    // Check if user has permission to view the discussion.
    if (!$discussion->access('view', $this->currentUser->getAccount())) {
      throw new AccessDeniedHttpException('You do not have permission to view this discussion.');
    }

    // Check if the discussion allows comments.
    if (!$discussion->hasField('field_replies') || !$discussion->get('field_replies')->status) {
      throw new BadRequestHttpException('Comments are not enabled for this discussion.');
    }

    try {
      // Get the comment type from field definition.
      $field_definition = $discussion->getFieldDefinition('field_replies');
      $comment_type = $field_definition->getSetting('comment_type');

      // Create the comment.
      $comment = Comment::create([
        'entity_type' => 'node',
        'entity_id' => $discussion_id,
        'field_name' => 'field_replies',
        'uid' => $this->currentUser->id(),
        'comment_type' => $comment_type,
        'subject' => $data['subject'],
        'comment_body' => [
          'value' => $data['comment_body'],
          'format' => $format,
        ],
        'status' => 1,
      ]);

      $comment->save();

      return new ModifiedResourceResponse([
        'message' => 'Comment created successfully.',
        'comment_id' => $comment->id(),
        'discussion_id' => $discussion_id,
      ], 201);
    }
    catch (\Exception $e) {
      $this->logger->error('Unexpected error creating comment via API: @message', [
        '@message' => $e->getMessage(),
      ]);
      throw new BadRequestHttpException('Failed to create comment: ' . $e->getMessage());
    }
  }

}
