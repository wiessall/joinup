<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Context\Context;
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
   * Selects the next month repeatedly until a specific month is reached.
   *
   * @param string $month
   *   The name of the month.
   *
   * @When I select the next month repeatedly until I reach :month
   */
  public function iSelectTheNextMonthRepeatedlyUntilIreach(string $month): void {
    $month = strtoupper($month);
    for ($i = 0; $i < 12; $i++) {
      $this->assertSession()->elementExists('css', '.pager__next')->click();
      $this->waitForAjax();
      $page = $this->getSession()->getPage();
      $calendar_element = $page->find('css', '.pager__current');
      if ($calendar_element && str_contains($calendar_element->getText(), $month)) {
        return;
      }
    }
  }

  /**
   * Selects the next month repeatedly until a specific week is reached.
   *
   * @param string $date
   *   The selected date.
   *
   * @When I select the next week repeatedly until I reach :date
   */
  public function iSelectTheNextWeekRepeatedlyUntilIreach(string $date): void {
    for ($i = 0; $i < 52; $i++) {
      $this->assertSession()->elementExists('css', '.pager__next')->click();
      $this->waitForAjax();
      $page = $this->getSession()->getPage();
      $locator = '.calendar-view-day time[datetime^="' . $date . '"]';
      $calendar_element = $page->find('css', $locator);
      if ($calendar_element) {
        return;
      }
    }
    throw new \Exception("No element for date " . $date . " was found. Please use format Y-m-d.");
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
    $eventElement = NULL;
    foreach ($elements as $element) {
      if (str_contains($element->getText(), $event)) {
        $eventElement = $element;
        break;
      }
    }

    if (!$eventElement) {
      throw new \Exception(sprintf("Element with class 'calendar-view-day' containing text '%s' was not found.", $event));
    }
  }

}
