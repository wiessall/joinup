/**
 * @file
 * Autocomplete additional behaviors for invite forms.
 */

((Drupal, once, $) => {
  /**
   * Alters the jQuery autocomplete for the invite form.
   *
   * This behavior is used by both the invitation form and the add form.
   * The 2 forms have different IDs but they are both based in the
   * InviteFormBase which passes the class 'invite-form' to the form.
   */
  Drupal.behaviors.inviteAutocomplete = {
    attach(context) {
      once("invite-autocomplete", "input.invite-autocomplete", context).forEach(
        (input) => {
          // Retrieve the jQuery UI autocomplete instance.
          const autocompleteWidget = $(input).data("ui-autocomplete");

          // Auto-submit the form after the selection is made by overriding the
          // select handler from web/core/misc/autocomplete.js.
          const originalSelectHandler = autocompleteWidget.options.select;
          autocompleteWidget.option("select", function (...args) {
            if (typeof originalSelectHandler === "function") {
              originalSelectHandler.apply(this, args);
            }

            input.closest("form").submit();
            return false;
          });

          // Allow typing straight away.
          input.focus();
        },
      );
    },
  };
})(Drupal, once, jQuery);
