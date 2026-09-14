<?php

namespace Drupal\ai_output_checks\Infrastructure\Drupal;

use Drupal\node\NodeInterface;

final class NodeAudit {

  public function appendLog(NodeInterface $node, string $event, string $message): void {
    if (!$node->hasField('field_logs')) {
      return;
    }

    //Valeria: next line: each log should go to a new instance of the field (if you concatenate in the same field, after a couple of runs the text becomes too long for the field}.:
    $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    //Valeria: line below: truncate $messsage:
    $mess = mb_strimwidth($message, 0, 1000, '...');   
    $line = sprintf("%s - %d - %s: %s", $ts, $node->id(), $event, $mess);

    // Get the format from the first existing item, or default to plain_text
    $firstItem = $node->get('field_logs')->first();
    $format = $firstItem && isset($firstItem->format) && $firstItem->format
     ? (string) $firstItem->format
     : 'plain_text';

    // Get all existing values
    $existingValues = $node->get('field_logs')->getValue();

    // Append the new entry as a new delta
    $existingValues[] = [
    'value' => $line,
    'format' => $format,
    ];

    $node->set('field_logs', $existingValues);

    /*
    $ts = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    $line = sprintf("%s - %d - %s: %s\n", $ts, $node->id(), $event, $message);

    $item = $node->get('field_logs')->first();
    $existing = $item ? (string) ($item->value ?? '') : '';
    $format = $item && isset($item->format) && $item->format ? (string) $item->format : 'plain_text';

    $node->set('field_logs', [
      'value' => $existing . $line,
      'format' => $format,
    ]);
  */
  }

  public function setNeedsReview(NodeInterface $node): void {
    if ($node->hasField('field_state')) {
      $node->set('field_state', 'Needs review');
    }
  }

  public function setRejectedBadAi(NodeInterface $node): void {
    if ($node->hasField('field_record_status')) {
      // Valeria: this needs an ID
      $node->set('field_record_status', '2072');
    }
    $this->setNeedsReview($node);
  }

}
