<?php

declare(strict_types=1);

namespace Joinup;

/**
 * Enumeration of possible test modes.
 */
enum TestMode: string {

  case PhpUnit = 'phpunit';
  case Behat = 'behat';
  case Playwright = 'playwright';

}
