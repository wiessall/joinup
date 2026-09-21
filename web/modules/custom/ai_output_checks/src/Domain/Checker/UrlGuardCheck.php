<?php

namespace Drupal\ai_output_checks\Domain\Checker;

use Drupal\ai_output_checks\Domain\ValueObject\CheckReport;
use Drupal\ai_output_checks\Domain\ValueObject\Finding;
use Drupal\ai_output_checks\Service\UrlChecker;

final class UrlGuardCheck implements AiCheckInterface {

  public function __construct(
    private readonly UrlChecker $urlChecker,
  ) {}

  public function id(): string {
    return 'url_guard';
  }

  public function supports(string $fieldName, string $fieldType): bool {
    return in_array($fieldType, [
      'link', 
    ], TRUE) || $fieldName === 'title';
  }

  private function looksLikeUrl(string $value): bool {
    $v = trim($value);
    return str_starts_with($v, 'http://') || str_starts_with($v, 'https://') || str_starts_with($v, 'www.');
  }

  private function normalizeUrl(string $value): string {
    $v = trim($value);
    return str_starts_with($v, 'www.') ? ('https://' . $v) : $v;
  }

  public function check(string $fieldName, array $values): CheckReport {
    $report = new CheckReport($fieldName);

    foreach ($values as $value) {
      $value = (string) $value;
      if (!$this->looksLikeUrl($value)) {
	// Valeria: if it continues, it validates wrong AI responses like "I cannot find a URL". Shouldn't it add a finding?      
	//continue;

        $report->add(new Finding(
          checkId: $this->id(),
          severity: 'error',
          message: 'URL check failed: bad AI response.',
          evidence: $value,
          data: [
            'normalized' => '',
            'httpStatus' => 'NA',
            'reason' => 'Bad AI response',
          ],
        ));
      }
      else {

      $url = $this->normalizeUrl($value);
      $res = $this->urlChecker->check($url);

      if (!$res->ok) {
        $report->add(new Finding(
          checkId: $this->id(),
          severity: 'error',
          message: $res->reason !== '' ? $res->reason : 'URL check failed.',
          evidence: $value,
          data: [
            'normalized' => $url,
            'httpStatus' => $res->httpStatus,
            'reason' => $res->reason,
          ],
        ));
      }
      }
    }

    return $report;
  }

}
