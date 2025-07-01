# Playwright Visual Regression Testing

This test suite performs **visual regression tests** on a clean Drupal installation using sample content provided by the `joinup_test_content` module. It captures screenshots of defined pages and compares them to **reference snapshots** stored in Git.

To ensure consistent rendering across different systems, tests are executed using **Selenium**, avoiding browser rendering variations (especially fonts) caused by hardware differences.

---

## Running Tests

1. **Install dependencies and set up the environment:**

   ```bash
   ddev install
   ```

2. **Run the visual regression tests:**

   ```bash
   ddev playwright test
   ```

   Test artifacts and results will be generated in the `test-results` directory.

3. **(Optional) View the interactive HTML test report:**

   ```bash
   ddev playwright show-report tests/playwright/playwright-report --host 0.0.0.0
   ```

   Then open:
   [https://your-project-name.ddev.local:9324/](https://your-project-name.ddev.local:9324/)
   *(Replace `your-project-name` with your actual project name.)*

---

## Updating Visual Snapshots

If legitimate changes have been made to the UI and you need to update the reference images:

```bash
ddev playwright test --update-snapshots
```

⚠️ **Before committing the updated snapshots, always verify** that the changes are expected and correct to avoid introducing false positives.

---

## Writing Custom Playwright Tests

You can create your own Playwright tests to extend the visual coverage of your Drupal site.

All Playwright test files should be placed under:
`tests/playwright/tests/`

### Example: Basic Visual Regression Test

Create a file like `home.spec.ts` inside the test directory:

```ts
import { test, expect } from '@playwright/test';

test('Homepage renders correctly', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveScreenshot('homepage.png');
});
```

This test:
- Navigates to the homepage (`/`)
- Captures a screenshot
- Compares it with `homepage.png` in the snapshot directory

### Snapshot Storage

Snapshots are automatically stored in:
`tests/playwright/tests/__screenshots__`

If there’s no existing snapshot, the test will fail until you run:

```bash
ddev playwright test --update-snapshots
```

### Additional Assertions

You can also test individual components or state changes:

```ts
test('Menu opens and displays all items', async ({ page }) => {
  await page.goto('/');
  await page.click('button[aria-label="Open menu"]');
  await expect(page.locator('.menu-items')).toBeVisible();
  await expect(page).toHaveScreenshot('menu-open.png');
});
```

---

## Recommended Folder Structure

```
tests/
└── playwright/
    └── tests/
        ├── home.spec.ts
        ├── home.spec.ts-snapshots
        ├── article.spec.ts
        ├── article.spec.ts-snapshots
        └── ...

```

---

## Best Practices

- Keep tests short and focused on one interaction or layout per file.
- Use `test.describe()` to group related tests.
- Always use stable selectors (e.g., data attributes like `data-testid`) to avoid flakiness.
- Avoid animations or time-based transitions unless explicitly testing them.
- Use viewport settings if responsive design is involved.

---

## Additional Resources

- 📘 **Playwright Documentation**: [https://playwright.dev](https://playwright.dev)
- 🐛 **Troubleshooting Tip**: If rendering inconsistencies occur, ensure you're not running browser tests directly from your host. All tests should run within the Selenium-controlled environment.
