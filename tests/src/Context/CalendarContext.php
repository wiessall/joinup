<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Behat\Context\Context;
use Behat\Mink\Exception\ElementNotFoundException;
use Drupal\Core\Datetime\DateHelper;
use Drupal\DrupalExtension\Context\RawDrupalContext;

/**
 * Defines application features from the specific context.
 */
class CalendarContext extends RawDrupalContext implements Context {

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
   * Selects the next month repeatedly until a specific month is reached.
   *
   * @When I select the next month repeatedly until I reach :month
   */
  public function iSelectTheNextMonthRepeatedlyUntilIreach($month): void {
    for ($i = 0; $i < 12; $i++) {
      $page = $this->getSession()->getPage();
      $nextLink = $page->find('css', '.pager__next');
      if (!$nextLink) {
        throw new ElementNotFoundException($this->getSession(), 'link', 'id|name|label', 'next');
      }
      $nextLink->click();
      $calendar_element = $page->find('css', '.pager_current');
      if ($calendar_element && strpos($calendar_element->getText(), $month) !== FALSE) {
        return;
      }
    }

    throw new \Exception(sprintf('Failed to navigate to the month %s.', $month));
  }

  /**
   * Verifies that a specific event is visible in the calendar.
   *
   * @Then I see the :event showing in the Calendar
   */
  public function iSeeTheEventShowingInTheCalendar($event): void {
    $page = $this->getSession()->getPage();
    $event_element = $page->find('css', '.calendar-view-day');
    if (!$event_element || strpos($event_element->getText(), $event) === FALSE) {
      throw new \Exception(sprintf('The event "%s" is not visible in the calendar.', $event));
    }
  }

  /**
   * Presses on a specific event in the calendar.
   *
   * @And I press on :event
   */
  public function iPressOnEvent($event): void {
    $page = $this->getSession()->getPage();
    $event_element = $page->findLink($event);
    if (!$event_element) {
      throw new ElementNotFoundException($this->getSession(), 'link', 'text', $event);
    }
    $event_element->click();
  }

}
