# Playwright Visual Regression Testing

This test suite performs **visual regression tests** on a clean Drupal
installation using sample content provided by the `joinup_test_content` module.
It captures screenshots of defined pages and compares them to **reference
snapshots** stored in Git.

To ensure consistent rendering across different systems, tests are executed
using **Selenium**, avoiding browser rendering variations (especially fonts)
caused by hardware differences.

---

## Running Tests

1. **Install a clean version of the site and setup for playwright:**

   ```bash
   ddev install
   ddev playwright setup
   ```

2. **Run the visual regression tests:**

   To run the full visual regression suite
   ```bash
   ddev playwright test
   ```

   Test artifacts and results will be generated in the `test-results` directory.

   > ℹ️ To explore available tests or run individual ones:
   >
   > - `ddev playwright test --help` — see all available options
   > - `ddev playwright test --headed` — run tests in headed mode, useful to visualize and debug
   > - `ddev playwright test [path_to_file]` — run a specific test file
   > - `ddev playwright test --list` — list available tests
   > - `ddev playwright test --grep ` — only run tests matching this regular
       expression (default: ".*")

3. **(Optional) View the interactive HTML test report:**

   ```bash
   ddev playwright show-report tests/playwright/playwright-report --host 0.0.0.0
   ```

   Then
   open: [https://[your-project-name].ddev.local:9324/](https://[your-project-name].ddev.local:9324/)

4. **(Optional) Teardown playwright setup:**

   ```bash
   ddev playwright teardown
   ```

---

## Updating Visual Snapshots

If legitimate changes have been made to the UI and you need to update the
reference images:

```bash
ddev playwright test --update-snapshots
```

⚠️ **Before committing the updated snapshots, always verify** that the changes
are expected and correct.

## Additional Resources

- 📘 **Playwright Documentation
  **: [https://playwright.dev](https://playwright.dev)
