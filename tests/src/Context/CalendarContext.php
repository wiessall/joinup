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
   * Navigates through calendar.
   *
   * @param string $date
   *   The selected date.
   * @param int $maxAttempts
   *   The maximum attempts for clicking selected pager link.
   * @param string $pagerSelector
   *   The pager selector: previous or next.
   *
   * @When I navigate calendar using :pagerSelector maximum :maxAttempts times until the date :date is displayed
   */
  public function iNavigateCalendarUsingMaximumTimesUntilTheDateIsDisplayed(string $date, int $maxAttempts, string $pagerSelector): void {
    $timestamp = strtotime($date);
    $locator = '.calendar-view-day time[datetime^="' . $date . '"]';
    $pagerSelector = strtolower($pagerSelector);
    $pagerSelector = '.pager__' . $pagerSelector;
    if (!$timestamp) {
      throw new \Exception(sprintf('"%s" is not correct date value.', $date));
    }

    while ($maxAttempts--) {
      $this->assertSession()->elementExists('css', $pagerSelector)->click();
      $this->waitForAjax();

      if ($this->getSession()->getPage()->find('css', $locator)) {
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
      throw new \Exception(sprintf("Unable to locate an element containing the text '%s'.", $event));
    }

  }

}
