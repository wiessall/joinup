/**
 * @file
 * Theme override for Drupal.theme.checkbox.
 *
 * @see @see Drupal.theme.checkbox
 */
((Drupal) => {
  Drupal.theme.checkbox = () => {
    return '<input type="checkbox" class="form-checkbox form-check-input"/>';
  };
})(Drupal);
