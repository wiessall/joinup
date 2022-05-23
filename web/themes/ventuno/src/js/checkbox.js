/**
 * @file
 * Theme override for Drupal.theme.checkbox.
 *
 * @see @see Drupal.theme.checkbox
 */
(function (Drupal) {
  Drupal.theme.checkbox = function () {
    return "<input type=\"checkbox\" class=\"form-checkbox form-check-input\"/>";
  };
})(Drupal);
