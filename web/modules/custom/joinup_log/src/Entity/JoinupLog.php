<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Entity;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the joinup log entity class.
 *
 * @ContentEntityType(
 *   id = "joinup_log",
 *   label = @Translation("Joinup log"),
 *   label_collection = @Translation("Joinup logs"),
 *   label_singular = @Translation("joinup log"),
 *   label_plural = @Translation("joinup logs"),
 *   label_count = @PluralTranslation(
 *     singular = "@count joinup log",
 *     plural = "@count joinup logs",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\joinup_log\JoinupLogStorage",
 *     "storage_schema" = "Drupal\joinup_log\JoinupLogStorageSchema",
 *     "views_data" = "Drupal\joinup_log\JoinupLogViewsData",
 *   },
 *   base_table = "joinup_log",
 *   admin_permission = "access site reports",
 *   entity_keys = {
 *     "id" = "id",
 *     "owner" = "uid",
 *   },
 * )
 */
final class JoinupLog extends ContentEntityBase implements JoinupLogInterface {

  use StringTranslationTrait, EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);
    $fields['uid']->setRequired(TRUE);

    $fields['entity_logged'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Entity logged'))
      ->setDescription(t('Entity this log entry relates to.'))
      ->setTargetEntityTypeId('rdf_entity')
      ->setSetting('target_type', 'rdf_entity');

    $fields['type'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Type'))
      ->setDescription(new TranslatableMarkup('Type of log message, for example "user" or "page not found."'))
      ->setRequired(TRUE);

    $fields['message'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Message'))
      ->setDescription(new TranslatableMarkup('Text of log message to be passed into the t() function.'))
      ->setRequired(TRUE);

    $fields['variables'] = BaseFieldDefinition::create('map')
      ->setLabel(new TranslatableMarkup('Variables'))
      ->setDescription(new TranslatableMarkup('Serialized array of variables that match the message string and that is passed into the t() function.'));

    $fields['severity'] = BaseFieldDefinition::create('integer')
      ->setLabel(new TranslatableMarkup('Severity'))
      ->setDescription(new TranslatableMarkup('The severity level of the event; ranges from 0 (Emergency) to 7 (Debug)'))
      ->setRequired(TRUE);

    $fields['location'] = BaseFieldDefinition::create('string_long')
      ->setLabel(new TranslatableMarkup('Location'))
      ->setDescription(new TranslatableMarkup('URL of the origin of the event.'));

    $fields['referer'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Referer'))
      ->setDescription(new TranslatableMarkup('URL of referring page.'));

    $fields['hostname'] = BaseFieldDefinition::create('string')
      ->setLabel(new TranslatableMarkup('Hostname'))
      ->setDescription(new TranslatableMarkup('Hostname of the user who triggered the event.'));

    $fields['timestamp'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Timestamp'))
      ->setDescription(new TranslatableMarkup('Unix timestamp of when event occurred.'))
      ->setRequired(TRUE);

    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLogged(): ?EntityInterface {
    return $this->get('entity_logged')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityLoggedId(): ?int {
    return $this->get('entity_logged')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getUid(): int {
    return (int) $this->getOwnerId();
  }

  /**
   * {@inheritdoc}
   */
  public function getType(): string {
    return $this->get('type')->getString();
  }

  /**
   * {@inheritdoc}
   */
  public function getSeverity(): int {
    return (int) $this->get('severity')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getLocation(): ?string {
    return $this->get('location')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getReferer(): ?string {
    return $this->get('referer')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getHostname(): ?string {
    return $this->get('hostname')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getTimestamp(): int {
    return (int) $this->get('timestamp')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormattedMessage(): MarkupInterface {
    $message = $this->get('message')->getString();
    $variables = $this->get('variables')->first()?->getValue() ?? [];
    if (!is_array($variables)) {
      return $this->formatMessage($message, NULL, NULL);
    }

    $backtrace_string = NULL;
    if (isset($variables['@backtrace_string'])) {
      $backtrace_string = '@backtrace_string';
    }
    return $this->formatMessage($message, $variables, $backtrace_string);
  }

  /**
   * {@inheritdoc}
   */
  private function formatMessage(string $message, ?array $variables, ?string $backtrace_string = NULL): MarkupInterface {
    if (empty($message) && empty($variables)) {
      return new FormattableMarkup('', []);
    }

    if (!is_array($variables)) {
      return $this->t('Log data is corrupted and cannot be unserialized: @message', ['@message' => Xss::filterAdmin($message)]);
    }

    // Ensure backtrace strings are properly formatted.
    if (!empty($backtrace_string) && isset($variables[$backtrace_string])) {
      $variables[$backtrace_string] = new FormattableMarkup(
        sprintf('<pre class="backtrace">@%s</pre>', $backtrace_string), $variables
      );
    }

    // phpcs:ignore Drupal.Semantics.FunctionT.NotLiteralString
    return $this->t(Xss::filterAdmin($message), $variables);
  }

}
