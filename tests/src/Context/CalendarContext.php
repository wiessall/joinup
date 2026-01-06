<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Context\Context;
use Behat\Step\When;
use DateInterval;
use DatePeriod;
use DateTime;
use Drupal\Core\Datetime\DateHelper;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\joinup\Traits\JavascriptTrait;

/**
 * Defines application features from the specific context.
 */
class CalendarContext extends RawDrupalContext implements Context {

  use JavascriptTrait;

  /**
   * Asserts that the calendar contains every day of the current month.
   *
   * @Then I should see the calendar showing all days of current month
   */
  public function iShouldSeeTheCalendarShowingAllDaysOfCurrentMonth(): void {
    $page = $this->getSession()->getPage();
    $currentYear = date('Y');
    $currentMonth = date('m');
    $daysInMonth = DateHelper::days($currentMonth, $currentYear);
    $dayElements = $page->findAll('css', "td[data-calendar-view-month='$currentMonth'][data-calendar-view-year='" . substr($currentYear, -2) . "']");

    foreach ($dayElements as $element) {
      $day = $element->getAttribute('data-calendar-view-day');
      if (!in_array((int) $day, $daysInMonth, TRUE)) {
        throw new \Exception(sprintf(
          'The calendar does not contain all days of the current month. Missing day: %d.',
          $day
        ));
      }
    }
  }

  /**
   * Asserts that the calendar contains every day of the current week.
   *
   * @Then I should see the calendar showing all days of current week
   */
  public function theCalendarContainsAllDaysOfCurrentWeek(): void {
    $today = new DateTime();
    $startOfWeek = (clone $today)->modify('this week');
    $endOfWeek = (clone $startOfWeek)->modify('+6 days');
    $expectedDays = iterator_to_array(
      (function () use ($startOfWeek, $endOfWeek) {
        $period = new DatePeriod($startOfWeek, new DateInterval('P1D'), $endOfWeek->modify('+1 day'));
        foreach ($period as $date) {
          yield (int) $date->format('d');
        }
      })()
    );

    $page = $this->getSession()->getPage();
    $calendarDayElements = $page->findAll('css', '.calendar-view-day__number');

    if (!$calendarDayElements) {
      throw new \Exception("No calendar day elements were found.");
    }
    $calendarDays = [];
    foreach ($calendarDayElements as $element) {
      $calendarDays[] = $element->getText();
    }
    $missingDays = array_diff($expectedDays, $calendarDays);
    if (!empty($missingDays)) {
      throw new \Exception(sprintf(
        "The calendar is missing the following days: %s",
        implode(', ', $missingDays)
      ));
    }
  }

  /**
   * Navigates the calendar to selected date.
   *
   * Examples:
   *   - I navigate the calendar to "2025-12-15".
   *   - I navigate the calendar to "2024-12-15".
   *
   * @param string $date
   *   The date string. This should either be in the format "DD-MM-YYYY"
   *    for dates, "HH:MM:SS" for time, or a relative date format.
   * @param string $mode
   *   Calendar mode: month or week.
   *
   * @throws \DateMalformedStringException
   */
  #[When('I navigate the calendar to :date in :mode mode')]
  public function iNavigateTheCalendarTo(string $date, string $mode): void {
    $pagerSelector = $this->getPagerByDate($date);
    $dateTimestamp = strtotime($date);
    switch (strtolower($mode)) {
      case 'month':
        $dateFormatted = date("Y-m-15", $dateTimestamp);
        $maxAttempts = $this->calculateMonthDifference($dateTimestamp);
        break;

      case 'week':
        $dateFormatted = date("Y-m-d", $dateTimestamp);
        $maxAttempts = $this->calculateWeekDifference($dateTimestamp);
        break;

      default:
        throw new \Exception('Invalid value. Use "week" or "month".');
    }
    if (!$this->isDateVisibleOnCurrentPage($dateFormatted)) {
      $maxAttempts = ($maxAttempts === 0) ? 1 : $maxAttempts;
      $xpath = sprintf('//div[contains(@class, "calendar-view-day")]//time[starts-with(@datetime, "%s")]', $dateFormatted);
      $this->navigateCalendar($xpath, $dateFormatted, $maxAttempts, $pagerSelector);
    }
  }

  /**
   * Returns pager selector dependently on passed date.
   *
   * @param string $date
   *   Passed date string.
   *
   * @throws \Exception
   */
  private function getPagerByDate(string $date): string {
    $passedTimestamp = strtotime($date);
    $currentTimestamp = strtotime(date('Y-m-d'));
    if ($passedTimestamp !== FALSE) {
      return ($passedTimestamp < $currentTimestamp) ? '.pager__previous' : '.pager__next';
    }
    throw new \Exception('Invalid value. Use valid date string (ex. 2025-02-01)',);
  }

  /**
   * Calculated interval between dates in months.
   *
   * @param int $endTimestamp
   *   The end of time interval.
   *
   * @throws \Exception
   */
  private function calculateMonthDifference(int $endTimestamp): int {
    $timeElement = $this->getSession()
      ->getPage()
      ->find('xpath', "//div[contains(@class, 'view-id-calendar')]//li[contains(@class, 'pager__current')]");
    if (!$timeElement) {
      throw new \Exception("Time element not found.");
    }
    $startDate = strtotime($timeElement->getText());
    $startYear = (int) date('Y', $startDate);
    $startMonth = (int) date('m', $startDate);
    $endYear = (int) date('Y', $endTimestamp);
    $endMonth = (int) date('m', $endTimestamp);

    return abs((($endYear - $startYear) * 12) + ($endMonth - $startMonth));
  }

  /**
   * Calculated interval between dates in weeks.
   *
   * @param int $endTimestamp
   *   The end of time interval.
   *
   * @throws \Exception
   */
  private function calculateWeekDifference(int $endTimestamp): int {
    $timeElement = $this->getSession()
      ->getPage()
      ->find('xpath', "(//div[@class='calendar-view-day']/time[@class='calendar-view-day__number'])[1]");
    if (!$timeElement) {
      throw new \Exception("Time element not found.");
    }
    $startTimestamp = strtotime(preg_replace('/UTC.*$/', '', $timeElement->getAttribute('datetime')));

    // Convert seconds to days.
    $days = (int) round(($endTimestamp - $startTimestamp) / (60 * 60 * 24));
    $quotient = intdiv($days, 7);

    return abs(($days % 7 === 0) ? $quotient : $quotient + 1);
  }

  /**
   * Checks if the date is visible on current calendar page.
   *
   * @param string $date
   *   The date expected to be found on current calendar page.
   */
  private function isDateVisibleOnCurrentPage(string $date): bool {
    $xpath = sprintf('//div[contains(@class, "calendar-view-day")]//time[starts-with(@datetime, "%s")]', $date);
    $timeElement = $this->getSession()->getPage()->find('xpath', $xpath);
    if (NULL === $timeElement) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Navigate through a calendar and look for a specific element.
   *
   * @param string $xpath
   *   The XPath expression to locate the target element.
   * @param string $date
   *   The date.
   * @param int $maxAttempts
   *   The maximum number of pager clicks to attempt.
   * @param string $pagerSelector
   *   The CSS selector for the pager control.
   *
   * @throws \Exception
   *   If the target element is not found after all attempts.
   */
  public function navigateCalendar(string $xpath, string $date, int $maxAttempts, string $pagerSelector): void {
    while ($maxAttempts-- > 0) {
      $this->assertSession()->elementExists('css', $pagerSelector)->click();
      $this->waitForAjax();
      if ($this->getSession()->getPage()->find('xpath', $xpath)) {
        return;
      }
    }
    throw new \Exception(sprintf('Unable to find the element matching "%s".', $date));
  }

  /**
   * Verifies that a specific event is visible in the calendar.
   *
   * @param string $event
   *   The name of the event.
   *
   * @Then I see the :event showing in the Calendar
   */
  public function iSeeTheEventShowingInTheCalendar(string $event): void {
    $page = $this->getSession()->getPage();
    $elements = $page->findAll('css', '.calendar-view-day');
    $eventElement = current(array_filter($elements, fn($element) => str_contains($element->getText(), $event))) ?: NULL;

    if (!$eventElement) {
      throw new \Exception(sprintf("Event '%s' is NOT visible in the calendar as expected.", $event));
    }
  }

}
