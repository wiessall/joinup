(function (factory) {
  typeof define === 'function' && define.amd ? define(factory) :
  factory();
})((function () { 'use strict';

  /**
   * @file
   * Theme override for Drupal.theme.checkbox.
   *
   * @see Drupal.theme.checkbox
   */
  (() => {
    Drupal.theme.checkbox = () => {
      return '<input type="checkbox" class="form-checkbox form-check-input"/>';
    };
  })();

}));
//# sourceMappingURL=checkbox.js.map
