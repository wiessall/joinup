/**
 * @file
 * Collects JS errors and deprecations (used in behat).
 *
 * @see \Drupal\joinup\Context\JsErrorsContext
 * @see js_testing_log.js from js_testing_log_test module
 */
(function () {
  function addSessionStorageRecord(key, value) {
    const data = JSON.parse(sessionStorage.getItem(key) || JSON.stringify([]));
    data.push(value);
    sessionStorage.setItem(key, JSON.stringify(data));
  }

  if (typeof console !== "undefined" && console.warn) {
    const originalWarn = console.warn;
    console.warn = (warning) => {
      originalWarn(warning);
      addSessionStorageRecord("joinup_test.js_warnings", {
        message: warning,
        url: window.location.href,
      });
    };
  }

  window.addEventListener("error", (evt) => {
    addSessionStorageRecord("joinup_test.js_errors", [
      `Message: ${evt.message}`,
      `URL: ${window.location.href}`,
      `File: ${evt.filename}`,
      `Line: ${evt.lineno}`,
      `Column ${evt.colno}`,
      `Stack: ${evt.error instanceof Error ? evt.error.stack : "N/A"}`,
    ]);
  });
})();
