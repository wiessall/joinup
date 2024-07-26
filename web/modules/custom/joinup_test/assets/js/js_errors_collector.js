/**
 * @file
 * Collects JS errors and deprecations (used in behat).
 *
 * @see \Drupal\joinup\Context\JsErrorsContext
 */
((Drupal) => {
  // Collect errors.
  Drupal.jsErrors = [];
  window.addEventListener("error", function (e) {
    Drupal.jsErrors.push([
      `Message: ${e.message}`,
      `URL: ${window.location.href}`,
      `File: ${e.filename}`,
      `Line: ${e.lineno}`,
      `Column ${e.colno}`,
      `Stack: ${e.error instanceof Error ? e.error.stack : "N/A"}`,
    ]);
  });

  // Collect deprecations.
  Drupal.jsDeprecations = [];
  const originalFn = Drupal.deprecationError;
  Drupal.deprecationError = ({ message }) => {
    originalFn(message);
    Drupal.jsDeprecations.push([
      `Deprecation: ${message}`,
      `URL: ${window.location.href}`,
    ]);
  };
})(Drupal);
